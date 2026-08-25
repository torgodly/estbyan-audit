@props([
    'titleId' => 'photo-requirements-title',
])

@php
    $uploads = \App\Support\RegistrationUploads::class;
@endphp

<aside {{ $attributes->class('reg-photo-requirements') }} aria-labelledby="{{ $titleId }}">
    <div class="reg-photo-requirements-head">
        <div class="reg-photo-requirements-icon" aria-hidden="true">
            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z"/>
            </svg>
        </div>
        <div class="min-w-0">
            <h3 id="{{ $titleId }}" class="reg-photo-requirements-title">{{ $uploads::requirementsTitle() }}</h3>
            <p class="reg-photo-requirements-intro">{{ $uploads::requirementsIntro() }}</p>
        </div>
    </div>

    <ul class="reg-photo-requirements-list">
        <li>صورة شخصية <strong>حديثة وواضحة</strong>، ويفضل ألا يتجاوز تاريخ التقاطها 6 أشهر.</li>
        <li>خلفية <strong>بيضاء أو رمادية فاتحة وسادة</strong>، خالية من النقوش والظلال.</li>
        <li>مواجهة الكاميرا بشكل مباشر، مع إبقاء الرأس مستقيماً وتعبير وجه محايد.</li>
        <li>يجب أن يكون <strong>الوجه بالكامل والعينان واضحتين</strong>، من أعلى الجبهة إلى أسفل الذقن.</li>
        <li>إضاءة متوازنة دون ظلال قوية أو انعكاسات على الوجه.</li>
        <li>يمنع استخدام <strong>صور السيلفي، الصور الجماعية، الصور غير الرسمية، أو الصور المأخوذة من مناسبات أو رحلات</strong>.</li>
        <li><strong>يمنع استخدام الفلاتر أو تعديلات التجميل أو معالجة الصورة بشكل مبالغ فيه.</strong></li>
        <li>النظارات الشمسية والعدسات الملونة <strong>غير مسموح بها</strong>. ويُسمح بالنظارات الطبية بشرط وضوح العينين وعدم وجود انعكاسات.</li>
        <li>يُسمح بالحجاب أو غطاء الرأس لأسباب دينية، بشرط ظهور الوجه بالكامل وعدم تغطية ملامحه.</li>
        <li>{{ $uploads::formatRequirement() }}</li>
        <li>يجب أن تكون جودة الصورة عالية وغير ضبابية.</li>
    </ul>

    <p class="reg-photo-requirements-note">
        <strong>ملاحظة:</strong> {{ $uploads::requirementsNote() }}
    </p>
</aside>
