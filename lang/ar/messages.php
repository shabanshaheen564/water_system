<?php

return [
    'app' => [
        'name' => 'نظام إدارة المياه ونظم المعلومات الجغرافية', 'municipality' => 'بلدية دير البلح', 'department' => 'دائرة المياه والصرف الصحي', 'section' => 'قسم نظم المعلومات الجغرافية',
    ],
    'roles' => [
        'System Owner' => 'مالك النظام', 'Admin' => 'مدير النظام', 'GIS Admin' => 'مدير نظم المعلومات الجغرافية', 'Engineer' => 'مهندس', 'Field Worker' => 'عامل ميداني', 'Viewer' => 'مستعرض',
    ],
    'permission_groups' => [
        'users' => 'المستخدمون', 'roles' => 'الأدوار', 'permissions' => 'الصلاحيات', 'complaints' => 'الشكاوى', 'tasks' => 'المهام', 'gis' => 'نظم المعلومات الجغرافية', 'assets' => 'الأصول', 'reports' => 'التقارير', 'audit_logs' => 'سجل التدقيق', 'datasets' => 'مجموعات البيانات',
    ],
    'permissions' => [
        'users.view' => 'عرض المستخدمين', 'users.create' => 'إنشاء المستخدمين', 'users.update' => 'تعديل المستخدمين', 'users.delete' => 'حذف المستخدمين',
        'roles.view' => 'عرض الأدوار', 'roles.create' => 'إنشاء الأدوار', 'roles.update' => 'تعديل الأدوار', 'roles.delete' => 'حذف الأدوار', 'permissions.view' => 'عرض الصلاحيات',
        'complaints.view' => 'عرض الشكاوى', 'complaints.create' => 'إنشاء الشكاوى', 'complaints.update' => 'تعديل الشكاوى', 'complaints.delete' => 'حذف الشكاوى', 'complaints.transition' => 'تغيير حالة الشكاوى', 'complaints.convert_to_task' => 'تحويل الشكاوى إلى مهام',
        'tasks.view' => 'عرض المهام', 'tasks.create' => 'إنشاء المهام', 'tasks.update' => 'تعديل المهام', 'tasks.delete' => 'حذف المهام', 'tasks.assign' => 'إسناد المهام', 'tasks.transition' => 'تغيير حالة المهام', 'tasks.update_status' => 'تحديث حالة المهام', 'tasks.view_updates' => 'عرض تحديثات المهام', 'tasks.create_update' => 'إضافة تحديثات المهام',
        'gis.view' => 'عرض نظم المعلومات الجغرافية', 'gis.layers.create' => 'إنشاء طبقات GIS', 'gis.layers.update' => 'تعديل طبقات GIS', 'gis.layers.delete' => 'حذف طبقات GIS', 'gis.fields.view' => 'عرض حقول GIS', 'gis.features.create' => 'إنشاء المعالم الجغرافية', 'gis.features.update' => 'تعديل المعالم الجغرافية', 'gis.features.delete' => 'حذف المعالم الجغرافية', 'gis.import' => 'استيراد البيانات الجغرافية',
        'assets.view' => 'عرض الأصول', 'assets.create' => 'إنشاء الأصول', 'assets.update' => 'تعديل الأصول', 'assets.delete' => 'حذف الأصول', 'reports.view' => 'عرض التقارير', 'reports.export' => 'تصدير التقارير', 'audit_logs.view' => 'عرض سجل التدقيق',
        'datasets.view' => 'عرض مجموعات البيانات', 'datasets.create' => 'إنشاء مجموعات البيانات', 'datasets.update' => 'تعديل مجموعات البيانات', 'datasets.delete' => 'حذف مجموعات البيانات',
    ],
    'user_management' => [
        'custom_permissions_help' => 'هذه الصلاحيات تطبق مباشرة على هذا الحساب بالإضافة إلى الصلاحيات الموروثة من دوره.', 'custom_permissions_separate' => 'الصلاحيات المسندة مباشرة إلى هذا الحساب، بشكل منفصل عن صلاحيات الدور.', 'no_custom_permissions' => 'لا توجد صلاحيات مخصصة مسندة.', 'protected_owner_permissions' => 'يمتلك مالك النظام كامل صلاحيات النظام بصورة محمية. تتم إدارة هذه الصلاحيات من خلال دور مالك النظام المحمي.', 'create_with_permissions' => 'إنشاء مستخدم جديد وإسناد الأدوار والصلاحيات المخصصة إليه', 'edit_with_permissions' => 'تحديث معلومات الحساب والحالة والأدوار والصلاحيات المخصصة',
    ],
    'roles_management' => [
        'edit_permissions' => 'تعديل الصلاحيات', 'edit_title' => 'تعديل صلاحيات الدور', 'edit_help' => 'إدارة الصلاحيات التي يرثها هذا الدور', 'protected' => 'محمي', 'protected_message' => 'دور مالك النظام محمي ولا يمكن تعديله.', 'updated' => 'تم تحديث صلاحيات الدور بنجاح.',
    ],
    'controllers' => [
        'dataset' => ['protected_configuration' => 'لا يمكن تغيير إعدادات النوع أو البيانات المكانية بعد وجود سجلات أو معالم أو علاقات مرتبطة بمجموعة البيانات.'],
    ],
    'public' => [
        'state' => 'دولة فلسطين', 'portal' => 'بوابة الموظفين والجهات المخولة', 'title' => 'نظام إدارة المياه ونظم المعلومات الجغرافية', 'description' => 'منصة مؤسسية لإدارة بيانات المياه والأصول المكانية وقواعد البيانات الجغرافية ضمن بيئة موحدة وآمنة.', 'central_data' => 'بيانات مركزية', 'central_data_desc' => 'إدارة منظمة للبيانات', 'gis' => 'نظم المعلومات الجغرافية', 'gis_desc' => 'إدارة البيانات المكانية', 'water_assets' => 'أصول المياه', 'water_assets_desc' => 'طبقات ومعلومات موثقة', 'login' => 'تسجيل الدخول إلى النظام', 'login_short' => 'دخول النظام', 'email' => 'البريد الإلكتروني', 'password' => 'كلمة المرور', 'email_placeholder' => 'أدخل البريد الإلكتروني', 'password_placeholder' => 'أدخل كلمة المرور', 'show_password' => 'إظهار كلمة المرور', 'hide_password' => 'إخفاء كلمة المرور', 'logging_in' => 'جارٍ تسجيل الدخول...', 'back_home' => 'العودة إلى الصفحة الرئيسية', 'home' => 'الصفحة الرئيسية',
    ],
    'navigation' => [
        'dashboard' => 'لوحة التحكم', 'datasets' => 'مجموعات البيانات', 'create_dataset' => 'إضافة مجموعة بيانات', 'map' => 'الخريطة الجغرافية', 'users' => 'المستخدمون', 'roles' => 'الأدوار', 'permissions' => 'الصلاحيات', 'logout' => 'تسجيل الخروج',
    ],
    'ui' => [
        'close_menu' => 'إغلاق القائمة', 'open_menu' => 'فتح القائمة', 'main_navigation' => 'التنقل الرئيسي',
    ],
];
