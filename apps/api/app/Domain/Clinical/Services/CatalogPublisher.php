<?php

namespace App\Domain\Clinical\Services;

use App\Domain\Compliance\Services\AuditLogger;
use App\Models\Service;
use App\Models\ServiceVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Catalog publication (PRD-03, decisions #65, #63, #96).
 *
 * Only a user holding `catalog.publish` — a licensed medical reviewer in production —
 * may publish. Publication freezes a version snapshot so historical bookings keep
 * the exact terms that were agreed.
 */
class CatalogPublisher
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function publish(Service $service, User $reviewer, ?string $changeNote = null): Service
    {
        if (! $reviewer->hasPermission('catalog.publish')) {
            throw new \DomainException('اعتماد نشر الخدمة يتطلب صلاحية المراجع الطبي');
        }

        $this->assertClinicallyComplete($service);

        return DB::transaction(function () use ($service, $reviewer, $changeNote) {
            $version = ((int) $service->versions()->max('version')) + 1;

            ServiceVersion::create([
                'service_id' => $service->id,
                'version' => $version,
                'snapshot' => $this->snapshot($service),
                'change_note' => $changeNote,
                'editor_user_id' => $reviewer->id,
            ]);

            $service->update([
                'status' => 'published',
                'published_by' => $reviewer->id,
                'published_at' => now(),
            ]);

            $this->audit->log('catalog.service_published', $reviewer, 'service', $service->public_id, 'clinical_governance', [
                'version' => $version,
            ]);

            return $service->fresh();
        });
    }

    /**
     * Suspending a published service stops NEW bookings while confirmed ones keep
     * their snapshot (AC-03). Recall of unperformed visits is a separate human action.
     */
    public function suspend(Service $service, User $reviewer, string $reason): Service
    {
        if (! $reviewer->hasPermission('catalog.publish')) {
            throw new \DomainException('تعليق الخدمة يتطلب صلاحية المراجع الطبي');
        }

        if (trim($reason) === '') {
            throw new \DomainException('تعليق خدمة منشورة يتطلب سببًا موثقًا');
        }

        $service->update(['status' => 'suspended']);

        $this->audit->log('catalog.service_suspended', $reviewer, 'service', $service->public_id, 'clinical_governance', [
            'reason' => $reason,
        ]);

        return $service->fresh();
    }

    /** A service cannot be published without the safety structure the rules require. */
    private function assertClinicallyComplete(Service $service): void
    {
        $missing = [];

        if (empty($service->name_ar) || empty($service->name_en)) {
            $missing[] = 'name';
        }

        if (! $service->profession_id) {
            $missing[] = 'profession';
        }

        if ($service->default_duration_minutes <= 0) {
            $missing[] = 'duration';
        }

        if (bccomp((string) $service->base_price, '0.00', 2) < 0) {
            $missing[] = 'price';
        }

        if ($missing !== []) {
            throw new \DomainException('لا يمكن نشر خدمة غير مكتملة: '.implode(', ', $missing));
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(Service $service): array
    {
        $service->loadMissing('procedures');

        return [
            'code' => $service->code,
            'name_ar' => $service->name_ar,
            'name_en' => $service->name_en,
            'category_id' => $service->category_id,
            'profession_id' => $service->profession_id,
            'billing_mode' => $service->billing_mode,
            'default_duration_minutes' => $service->default_duration_minutes,
            'base_price' => (string) $service->base_price,
            'currency' => $service->currency,
            'requires_medical_order' => $service->requires_medical_order,
            'required_fields' => $service->required_fields,
            'red_flag_screen' => $service->red_flag_screen,
            'allowed_age_range' => $service->allowed_age_range,
            'procedures' => $service->procedures->map(fn ($p) => [
                'code' => $p->code,
                'name_ar' => $p->name_ar,
                'name_en' => $p->name_en,
                'price' => (string) $p->price,
                'billing_mode' => $p->billing_mode,
            ])->all(),
        ];
    }
}