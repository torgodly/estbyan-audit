<section class="reg-card">
    <div class="reg-identity-card mb-6">
        <p class="text-center text-[11px] font-bold tracking-wide text-teal-300/90">بيانات الهوية</p>
        <p class="mt-2 text-center text-lg font-extrabold leading-snug sm:text-xl">{{ $verifiedFullName }}</p>
        <dl class="mt-4 grid grid-cols-2 gap-2.5 text-center sm:gap-3">
            <div class="rounded-xl bg-white/10 px-2.5 py-3">
                <dt class="text-[11px] font-medium text-slate-300">الرقم التأميني</dt>
                <dd class="mt-1 text-sm font-bold tracking-wide sm:text-base" dir="ltr">{{ $employeeNumber }}</dd>
            </div>
            <div class="rounded-xl bg-white/10 px-2.5 py-3">
                <dt class="text-[11px] font-medium text-slate-300">الرقم الوطني</dt>
                <dd class="mt-1 truncate text-sm font-bold tracking-wide sm:text-base" dir="ltr">{{ $nationalId }}</dd>
            </div>
        </dl>
    </div>

    <div class="reg-card-header !mb-5 !pb-0 !border-0">
        <h2 class="reg-card-title">بيانات الموظف</h2>
        <p class="reg-card-subtitle">أكمل معلوماتك الوظيفية والشخصية</p>
    </div>

    <div class="space-y-5">
        <div class="reg-grid-2">
            <div>
                <label class="reg-label">مكان العمل <span class="reg-required">*</span></label>
                <x-reg-searchable-select
                    wire:model.live="workplace"
                    :options="$workplaces"
                    placeholder="— اختر مكان العمل —"
                    search-placeholder="ابحث عن مكان العمل..."
                    data-reg-field="workplace"
                    @class(['reg-input-invalid' => $errors->has('workplace')])
                />
                @error('workplace') <p class="reg-field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="reg-label">الصفة</label>
                <div class="reg-input flex items-center bg-slate-50 font-bold text-slate-800">
                    موظف
                </div>
            </div>
        </div>

        <div class="reg-grid-2">
            <div>
                <label class="reg-label">تاريخ الميلاد <span class="reg-required">*</span></label>
                <input
                    wire:model.blur="dateOfBirth"
                    type="date"
                    data-reg-field="dateOfBirth"
                    @class(['reg-input', 'reg-input-invalid' => $errors->has('dateOfBirth')])
                >
                @if (\App\Support\LibyanNationalId::isValid($nationalId))
                    <p class="mt-1 text-xs text-slate-400">يجب أن تكون سنة الميلاد {{ \App\Support\LibyanNationalId::birthYear($nationalId) }} حسب الرقم الوطني</p>
                @endif
                @error('dateOfBirth') <p class="reg-field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="reg-label">الجنس <span class="reg-required">*</span></label>
                <div class="reg-input bg-slate-50 font-bold text-navy-900" data-reg-field="gender">
                    {{ $gender === 'female' ? 'أنثى' : 'ذكر' }}
                </div>
                <p class="mt-1 text-xs text-slate-400">يُستخرج تلقائياً من الرقم الوطني ولا يمكن تعديله</p>
                @error('gender') <p class="reg-field-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="reg-label">الحالة الاجتماعية <span class="reg-required">*</span></label>
            <select wire:model.live="maritalStatus" data-reg-field="maritalStatus" @class(['reg-select', 'reg-input-invalid' => $errors->has('maritalStatus')])>
                <option value="single">أعزب / عزباء</option>
                <option value="married">متزوج / متزوجة</option>
            </select>
            <p class="mt-1 text-xs text-slate-400">
                @if ($maritalStatus === 'married')
                    @if ($gender === 'female')
                        المستفيدون يُضافون لاحقاً: زوج واحد، وأبناء، وأب واحد، وأم واحدة
                    @else
                        المستفيدون يُضافون لاحقاً: حتى 4 زوجات، وأبناء، وأب واحد، وأم واحدة
                    @endif
                @else
                    المستفيدون يُضافون لاحقاً: أب واحد وأم واحدة
                @endif
            </p>
            @error('maritalStatus') <p class="reg-field-error">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="mt-8">
        <h3 class="reg-section-title">
            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z"/></svg>
            معلومات التواصل
        </h3>
        <div class="space-y-4">
            <div class="reg-grid-2">
                <div>
                    <label class="reg-label">رقم الهاتف <span class="reg-required">*</span></label>
                    <input
                        wire:model.blur="phone"
                        type="tel"
                        inputmode="numeric"
                        maxlength="10"
                        pattern="09[1-4][0-9]{7}"
                        data-reg-field="phone"
                        @class(['reg-input', 'reg-input-invalid' => $errors->has('phone')])
                        placeholder="091XXXXXXX"
                        dir="ltr"
                        x-on:input="$el.value = $el.value.replace(/\D+/g, '').slice(0, 10)"
                    >
                    <p class="mt-1 text-xs text-slate-400">{{ \App\Support\LibyanPhoneNumber::HINT }}</p>
                    @error('phone') <p class="reg-field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="reg-label">واتساب</label>
                    <input
                        wire:model.blur="whatsapp"
                        type="tel"
                        inputmode="numeric"
                        maxlength="10"
                        pattern="09[1-4][0-9]{7}"
                        data-reg-field="whatsapp"
                        @class(['reg-input', 'reg-input-invalid' => $errors->has('whatsapp')])
                        placeholder="091XXXXXXX"
                        dir="ltr"
                        x-on:input="$el.value = $el.value.replace(/\D+/g, '').slice(0, 10)"
                    >
                    <p class="mt-1 text-xs text-slate-400">اختياري — نفس صيغة رقم الهاتف</p>
                    @error('whatsapp') <p class="reg-field-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label class="reg-label">البريد الإلكتروني</label>
                <input
                    wire:model.blur="email"
                    type="email"
                    dir="ltr"
                    data-reg-field="email"
                    @class(['reg-input', 'text-left', 'reg-input-invalid' => $errors->has('email')])
                >
                @error('email') <p class="reg-field-error">{{ $message }}</p> @enderror
            </div>
            <div class="reg-grid-2">
                <div>
                    <label class="reg-label">المدينة <span class="reg-required">*</span></label>
                    <x-reg-searchable-select
                        wire:model.live="city"
                        :options="$cities"
                        placeholder="— اختر المدينة —"
                        search-placeholder="ابحث عن المدينة..."
                        data-reg-field="city"
                        @class(['reg-input-invalid' => $errors->has('city')])
                    />
                    @error('city') <p class="reg-field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="reg-label">العنوان السكني <span class="reg-required">*</span></label>
                    <input
                        wire:model.blur="address"
                        type="text"
                        data-reg-field="address"
                        @class(['reg-input', 'reg-input-invalid' => $errors->has('address')])
                        x-on:input="$el.value = $el.value.replace(/[0-9٠-٩]/g, '')"
                    >
                    <p class="mt-1 text-xs text-slate-400">بدون أرقام — مثال: حي الأندلس، شارع الجمهورية</p>
                    @error('address') <p class="reg-field-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>
    </div>
</section>

@include('livewire.registration.partials.actions', [
    'primaryAction' => 'saveEmployeeDetails',
    'primaryLabel' => 'حفظ ومتابعة',
    'primaryTarget' => 'saveEmployeeDetails',
    'loadingLabel' => 'جاري الحفظ...',
])
