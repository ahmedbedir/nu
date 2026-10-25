<?php

namespace App\Domain\Identity\Support;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

/**
 * Canonical RBAC catalogue (decisions #206, #207).
 *
 * Reference data only — synced idempotently. This is NOT a destructive seeder:
 * it upserts roles/permissions and never removes data.
 */
class RbacCatalog
{
    /** @return array<string, array{name_ar: string, name_en: string, scope: string}> */
    public static function roles(): array
    {
        return [
            'customer' => ['name_ar' => 'عميل', 'name_en' => 'Customer', 'scope' => 'customer'],
            'delegate' => ['name_ar' => 'مفوّض', 'name_en' => 'Delegate', 'scope' => 'customer'],
            'professional' => ['name_ar' => 'ممارس صحي', 'name_en' => 'Healthcare professional', 'scope' => 'professional'],
            'medical_reviewer' => ['name_ar' => 'مراجع طبي', 'name_en' => 'Medical reviewer', 'scope' => 'staff'],
            'document_verifier' => ['name_ar' => 'مدقق مستندات', 'name_en' => 'Document verifier', 'scope' => 'staff'],
            'privacy_officer' => ['name_ar' => 'مسؤول خصوصية', 'name_en' => 'Privacy officer', 'scope' => 'staff'],
            'financial_manager' => ['name_ar' => 'مدير مالي', 'name_en' => 'Financial manager', 'scope' => 'staff'],
            'ops_agent' => ['name_ar' => 'موظف عمليات', 'name_en' => 'Operations agent', 'scope' => 'staff'],
            'regional_manager' => ['name_ar' => 'مدير منطقة', 'name_en' => 'Regional manager', 'scope' => 'staff'],
            'admin' => ['name_ar' => 'أدمن', 'name_en' => 'Administrator', 'scope' => 'staff'],
            'audit_officer' => ['name_ar' => 'مسؤول تدقيق', 'name_en' => 'Audit officer', 'scope' => 'staff'],
            'super_admin' => ['name_ar' => 'أدمن أعلى', 'name_en' => 'Super administrator', 'scope' => 'system'],
        ];
    }

    /** @return array<string, array{name_ar: string, name_en: string, group: string}> */
    public static function permissions(): array
    {
        return [
            'patients.view' => ['name_ar' => 'عرض المرضى', 'name_en' => 'View patients', 'group' => 'patients'],
            'patients.manage' => ['name_ar' => 'إدارة المرضى', 'name_en' => 'Manage patients', 'group' => 'patients'],
            'patients.view_medical' => ['name_ar' => 'عرض السجل الطبي', 'name_en' => 'View medical record', 'group' => 'patients'],

            'professionals.review' => ['name_ar' => 'مراجعة الممارسين', 'name_en' => 'Review professionals', 'group' => 'professionals'],
            'professionals.verify_documents' => ['name_ar' => 'تدقيق المستندات', 'name_en' => 'Verify documents', 'group' => 'professionals'],
            'professionals.suspend' => ['name_ar' => 'إيقاف ممارس', 'name_en' => 'Suspend professional', 'group' => 'professionals'],

            'catalog.manage' => ['name_ar' => 'إدارة كتالوج الخدمات', 'name_en' => 'Manage service catalog', 'group' => 'catalog'],
            'catalog.publish' => ['name_ar' => 'اعتماد نشر خدمة', 'name_en' => 'Publish service', 'group' => 'catalog'],

            'bookings.view' => ['name_ar' => 'عرض الحجوزات', 'name_en' => 'View bookings', 'group' => 'bookings'],
            'bookings.manage' => ['name_ar' => 'إدارة الحجوزات', 'name_en' => 'Manage bookings', 'group' => 'bookings'],
            'bookings.reassign' => ['name_ar' => 'إعادة إسناد', 'name_en' => 'Reassign booking', 'group' => 'bookings'],

            'visits.view' => ['name_ar' => 'عرض الزيارات', 'name_en' => 'View visits', 'group' => 'visits'],
            'visits.override_timer' => ['name_ar' => 'تصحيح عدّاد الزيارة', 'name_en' => 'Override visit timer', 'group' => 'visits'],

            'sos.ack' => ['name_ar' => 'استلام استغاثة', 'name_en' => 'Acknowledge SOS', 'group' => 'sos'],
            'sos.escalate' => ['name_ar' => 'تصعيد استغاثة', 'name_en' => 'Escalate SOS', 'group' => 'sos'],
            'sos.close' => ['name_ar' => 'إغلاق استغاثة', 'name_en' => 'Close SOS', 'group' => 'sos'],

            'finance.view' => ['name_ar' => 'عرض المالية', 'name_en' => 'View finance', 'group' => 'finance'],
            'finance.approve_refund' => ['name_ar' => 'اعتماد استرداد', 'name_en' => 'Approve refund', 'group' => 'finance'],
            'finance.payout' => ['name_ar' => 'الصرف للممارسين', 'name_en' => 'Run provider payout', 'group' => 'finance'],
            'finance.dispute_manage' => ['name_ar' => 'إدارة المنازعات المالية', 'name_en' => 'Manage financial disputes', 'group' => 'finance'],

            'ops.dashboard' => ['name_ar' => 'لوحة العمليات', 'name_en' => 'Operations dashboard', 'group' => 'ops'],
            'ops.intervene' => ['name_ar' => 'تدخل تشغيلي', 'name_en' => 'Operational intervention', 'group' => 'ops'],

            'admin.roles.manage' => ['name_ar' => 'إدارة الأدوار', 'name_en' => 'Manage roles', 'group' => 'admin'],
            'admin.settings.manage' => ['name_ar' => 'إدارة الإعدادات', 'name_en' => 'Manage settings', 'group' => 'admin'],
            'audit.view' => ['name_ar' => 'عرض التدقيق', 'name_en' => 'View audit trail', 'group' => 'admin'],

            'privacy.break_glass' => ['name_ar' => 'وصول استثنائي للملف', 'name_en' => 'Break-glass access', 'group' => 'privacy'],
            'privacy.requests.manage' => ['name_ar' => 'طلبات الخصوصية', 'name_en' => 'Manage privacy requests', 'group' => 'privacy'],
        ];
    }

    /** @return array<string, string[]> role => permission codes */
    public static function grants(): array
    {
        return [
            'customer' => ['patients.view', 'patients.manage'],
            'delegate' => ['patients.view'],
            'professional' => ['visits.view'],
            'medical_reviewer' => ['patients.view_medical', 'catalog.publish', 'catalog.manage', 'professionals.review'],
            'document_verifier' => ['professionals.verify_documents', 'professionals.review'],
            'privacy_officer' => ['privacy.requests.manage', 'privacy.break_glass', 'audit.view'],
            'financial_manager' => ['finance.view', 'finance.approve_refund', 'finance.payout', 'finance.dispute_manage'],
            'ops_agent' => ['ops.dashboard', 'ops.intervene', 'sos.ack', 'sos.escalate', 'bookings.view', 'bookings.reassign', 'visits.view'],
            'regional_manager' => ['ops.dashboard', 'bookings.view', 'bookings.manage', 'visits.view', 'sos.escalate'],
            'admin' => ['admin.roles.manage', 'admin.settings.manage', 'audit.view', 'catalog.manage', 'bookings.manage', 'professionals.suspend'],
            'audit_officer' => ['audit.view'],
            'super_admin' => ['*'],
        ];
    }

    /**
     * Idempotent sync. Safe to run against the protected `nu` database.
     *
     * @return array{roles: int, permissions: int, grants: int}
     */
    public static function sync(): array
    {
        return DB::transaction(function () {
            $roleModels = [];

            foreach (self::roles() as $code => $meta) {
                $roleModels[$code] = Role::updateOrCreate(
                    ['code' => $code],
                    [...$meta, 'is_system' => in_array($code, ['super_admin', 'admin', 'customer'], true)],
                );
            }

            $permissionModels = [];
            foreach (self::permissions() as $code => $meta) {
                $permissionModels[$code] = Permission::updateOrCreate(['code' => $code], $meta);
            }

            $grants = 0;
            foreach (self::grants() as $roleCode => $codes) {
                if (! isset($roleModels[$roleCode])) {
                    continue;
                }

                if ($codes === ['*']) {
                    $ids = collect($permissionModels)->pluck('id')->all();
                } else {
                    $ids = collect($codes)
                        ->map(fn ($c) => $permissionModels[$c]->id ?? null)
                        ->filter()
                        ->all();
                }

                $roleModels[$roleCode]->permissions()->sync($ids);
                $grants += count($ids);
            }

            return [
                'roles' => count($roleModels),
                'permissions' => count($permissionModels),
                'grants' => $grants,
            ];
        });
    }
}