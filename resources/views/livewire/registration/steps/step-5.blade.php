<section class="reg-card">
    <div class="reg-card-header">
        <h2 class="reg-card-title">إرفاق المستندات</h2>
        <p class="reg-card-subtitle">ارفع الصورة الشخصية المطلوبة لإتمام التسجيل.</p>
    </div>

    <div class="space-y-5">
        <x-reg-photo-requirements title-id="employee-photo-requirements" />

        <div>
            <p class="reg-label">الصورة الشخصية للموظف <span class="reg-required">*</span></p>
            <p class="mt-1 text-xs text-slate-500">مطلوبة لإصدار بطاقة التأمين</p>

            @php
                $employeePhotoPreview = $this->temporaryUploadPreviewUrl($employeePhoto);
                $employeeSavedPhoto = $this->employeeSavedPhotoUrl();
                $employeeHasPhoto = (bool) ($employeePhotoPreview || $employeeSavedPhoto || $hasEmployeePhoto);
            @endphp
            <label
                data-reg-field="employeePhoto"
                @class([
                    'reg-photo-dropzone mt-3',
                    'reg-photo-dropzone-filled' => $employeeHasPhoto,
                    'reg-photo-dropzone-invalid' => $errors->has('employeePhoto'),
                ])
            >
                @if ($employeePhotoPreview)
                    <div class="reg-photo-dropzone-frame">
                        <img src="{{ $employeePhotoPreview }}" alt="معاينة صورة الموظف" class="size-full object-cover">
                        <span class="reg-photo-badge">معاينة جديدة</span>
                    </div>
                @elseif ($employeeSavedPhoto)
                    <div class="reg-photo-dropzone-frame">
                        <img src="{{ $employeeSavedPhoto }}" alt="صورة الموظف" class="size-full object-cover">
                        <span class="reg-photo-badge">محفوظة</span>
                    </div>
                @elseif ($hasEmployeePhoto)
                    <div class="reg-photo-dropzone-frame">
                        <span class="reg-photo-badge">محفوظة</span>
                    </div>
                @else
                    <div class="reg-photo-dropzone-icon" aria-hidden="true">
                        <svg class="size-8" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z"/></svg>
                    </div>
                @endif

                <div class="reg-photo-dropzone-copy">
                    <p class="reg-photo-dropzone-title">
                        <span wire:loading.remove wire:target="employeePhoto">
                            {{ $employeeHasPhoto ? 'تم اختيار صورة الموظف' : 'اضغط هنا لاختيار الصورة الشخصية' }}
                        </span>
                        <span wire:loading wire:target="employeePhoto">جاري رفع الصورة…</span>
                    </p>
                    <p class="reg-photo-dropzone-hint">{{ \App\Support\RegistrationUploads::sizeHint() }} — الوجه واضح على خلفية بيضاء</p>
                    <span class="reg-photo-dropzone-cta">
                        {{ $employeeHasPhoto ? 'تغيير الصورة' : 'اختيار صورة' }}
                    </span>
                </div>

                <x-reg-photo-input property="employeePhoto" label="صورة الموظف" />
            </label>
            @error('employeePhoto') <p class="reg-field-error mt-2">{{ $message }}</p> @enderror
        </div>
    </div>
</section>

@include('livewire.registration.partials.actions', [
    'primaryAction' => 'saveDocuments',
    'primaryLabel' => 'متابعة للمراجعة',
    'primaryTarget' => 'saveDocuments',
    'loadingLabel' => 'جاري الحفظ...',
])
