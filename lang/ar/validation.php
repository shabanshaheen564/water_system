<?php

return [
    'required' => 'حقل :attribute مطلوب.',
    'string' => 'يجب أن يكون :attribute نصاً.',
    'email' => 'يجب أن يكون :attribute عنوان بريد إلكتروني صحيحاً.',
    'max' => ['string' => 'يجب ألا يتجاوز :attribute :max حرفاً.', 'array' => 'يجب ألا يحتوي :attribute على أكثر من :max عناصر.', 'file' => 'يجب ألا يتجاوز حجم :attribute :max كيلوبايت.', 'numeric' => 'يجب ألا تكون قيمة :attribute أكبر من :max.'],
    'min' => ['string' => 'يجب ألا يقل :attribute عن :min أحرف.', 'array' => 'يجب أن يحتوي :attribute على :min عناصر على الأقل.', 'numeric' => 'يجب ألا تقل قيمة :attribute عن :min.'],
    'unique' => 'قيمة :attribute مستخدمة بالفعل.',
    'exists' => 'القيمة المحددة في :attribute غير صحيحة.',
    'integer' => 'يجب أن يكون :attribute عدداً صحيحاً.',
    'numeric' => 'يجب أن يكون :attribute رقماً.',
    'boolean' => 'يجب أن تكون قيمة :attribute صحيحة أو خاطئة.',
    'array' => 'يجب أن يكون :attribute مصفوفة.',
    'date' => 'يجب أن يكون :attribute تاريخاً صحيحاً.',
    'in' => 'القيمة المحددة في :attribute غير صحيحة.',
    'same' => 'يجب أن يتطابق :attribute مع :other.',
    'confirmed' => 'تأكيد :attribute غير متطابق.',
    'required_if' => 'حقل :attribute مطلوب عندما تكون :other هي :value.',
    'required_with' => 'حقل :attribute مطلوب عند وجود :values.',
    'required_without' => 'حقل :attribute مطلوب عند عدم وجود :values.',
    'attributes' => [],
];
