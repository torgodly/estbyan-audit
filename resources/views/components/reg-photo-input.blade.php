@props([
    'property',
    'label' => 'الصورة',
    'accept' => 'image/jpeg,image/png,image/jpg',
])

@php
    $maxBytes = \App\Support\RegistrationUploads::MAX_KILOBYTES * 1024;
    $tooLarge = \App\Support\RegistrationUploads::tooLargeMessage($label);
    $invalidType = \App\Support\RegistrationUploads::invalidTypeMessage($label);
    $failed = \App\Support\RegistrationUploads::failedMessage($label);
@endphp

<input
    type="file"
    accept="{{ $accept }}"
    class="sr-only"
    x-on:change="
        const input = $event.target;
        const file = input.files?.[0];
        if (! file) {
            return;
        }

        const allowed = ['image/jpeg', 'image/png', 'image/jpg'];
        if (file.type && ! allowed.includes(file.type)) {
            input.value = '';
            $wire.reportUploadClientError(@js($property), @js($invalidType));
            return;
        }

        if (file.size > {{ $maxBytes }}) {
            input.value = '';
            $wire.reportUploadClientError(@js($property), @js($tooLarge));
            return;
        }

        $wire.upload(
            @js($property),
            file,
            () => { input.value = ''; },
            () => {
                input.value = '';
                $wire.reportUploadClientError(@js($property), @js($failed));
            },
            () => {},
        );
    "
    {{ $attributes }}
>
