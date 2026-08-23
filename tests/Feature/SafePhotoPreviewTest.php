<?php

use App\Livewire\MedicalRegistrationForm;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\FileNotPreviewableException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Livewire;

it('never throws when building a preview url for a broken temp upload', function () {
    Storage::fake('tmp-for-tests');
    Storage::disk('tmp-for-tests')->put('livewire-tmp/no-extension', 'x');

    $file = TemporaryUploadedFile::createFromLivewire('no-extension');

    expect($file->isPreviewable())->toBeFalse()
        ->and(fn () => $file->temporaryUrl())->toThrow(FileNotPreviewableException::class);

    $component = Livewire::test(MedicalRegistrationForm::class);

    expect($component->instance()->temporaryUploadPreviewUrl($file))->toBeNull()
        ->and($component->instance()->temporaryUploadPreviewUrl(null))->toBeNull()
        ->and($component->instance()->temporaryUploadPreviewUrl('bad'))->toBeNull();
});

it('renders a soft livewire response for FileNotPreviewableException', function () {
    Storage::fake('tmp-for-tests');
    Storage::disk('tmp-for-tests')->put('livewire-tmp/no-extension', 'x');

    $exception = new FileNotPreviewableException(
        TemporaryUploadedFile::createFromLivewire('no-extension'),
    );

    $request = Request::create('/register', 'POST');
    $request->headers->set('X-Livewire', '1');

    $response = app(ExceptionHandler::class)
        ->render($request, $exception);

    expect($response->getStatusCode())->toBe(422)
        ->and(json_decode($response->getContent(), true)['message'] ?? null)
        ->toBe('تعذر عرض الصورة. أعد رفع ملف JPG أو PNG.');
});
