<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

final class InsuranceCardSvg
{
    /**
     * Right edges of the value column, just left of the outlined labels.
     *
     * @var array<string, array{x: float, y: float, anchor: string, size: string}>
     */
    private const TEXT_LAYOUT = [
        'card-number' => ['x' => 400.0, 'y' => 198.0, 'anchor' => 'end', 'size' => '32px'],
        'card-name' => ['x' => 400.0, 'y' => 252.0, 'anchor' => 'end', 'size' => '24px'],
        'card-dob' => ['x' => 400.0, 'y' => 312.0, 'anchor' => 'end', 'size' => '24px'],
        'card-job' => ['x' => 400.0, 'y' => 368.0, 'anchor' => 'end', 'size' => '24px'],
        'card-issued' => ['x' => 603.6, 'y' => 569.76, 'anchor' => 'start', 'size' => '24px'],
    ];

    /**
     * @var list<array{text: string, y: float}>
     */
    private const FIELD_LABELS = [
        ['text' => 'رقم البطاقة:', 'y' => 198.0],
        ['text' => 'الاسم:', 'y' => 252.0],
        ['text' => 'تاريخ الميلاد:', 'y' => 312.0],
        ['text' => 'الصفة:', 'y' => 368.0],
    ];

    public static function front(EmployeeInsuranceCard $card, bool $embedAssets = true, bool $includePhoto = true): string
    {
        $path = public_path('cards/card-front-aud.svg');

        if (! is_readable($path)) {
            throw new RuntimeException('Missing insurance card artwork: card-front-aud.svg');
        }

        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = true;
        $loaded = $dom->loadXML((string) file_get_contents($path));

        if ($loaded === false) {
            throw new RuntimeException('Unable to parse insurance card artwork.');
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('svg', 'http://www.w3.org/2000/svg');

        self::setText($xpath, 'card-number', $card->reference);
        self::setText($xpath, 'card-name', $card->name);
        self::setText($xpath, 'card-dob', $card->dateOfBirth);
        self::setText($xpath, 'card-job', $card->jobTitle);
        self::setText($xpath, 'card-issued', $card->issuedAt);
        self::hide($xpath, 'card-blood');
        self::replaceFieldLabels($dom, $xpath);

        self::setPhoto($xpath, $includePhoto ? ($card->photoDataUri ?: $card->photoSrc($embedAssets)) : null);
        self::setBarcode($dom, $xpath, $card->barcodeSvg);
        self::embedFont($xpath, $embedAssets ? $card->fontDataUri : $card->fontUrl);

        $root = $dom->documentElement;
        $root->setAttribute('width', '972.22');
        $root->setAttribute('height', '601.8');

        $svg = $dom->saveXML($root);

        if (! is_string($svg) || $svg === '') {
            throw new RuntimeException('Unable to render insurance card artwork.');
        }

        return self::uniquifyMarkup($svg, $card->personKey);
    }

    public static function frontDataUri(EmployeeInsuranceCard $card): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode(self::front($card, embedAssets: true, includePhoto: false));
    }

    private static function setText(DOMXPath $xpath, string $id, string $value): void
    {
        $element = self::element($xpath, $id);

        if (! $element instanceof DOMElement) {
            return;
        }

        $layout = self::TEXT_LAYOUT[$id];
        $element->setAttribute('text-anchor', $layout['anchor']);
        $element->setAttribute('transform', 'translate('.$layout['x'].' '.$layout['y'].')');
        $element->setAttribute('font-size', $layout['size']);
        $element->setAttribute('style', 'font-size: '.$layout['size']);

        if ($element->hasAttribute('class')) {
            $element->setAttribute('class', (string) preg_replace('/\bcls-9\b/', 'cls-8', $element->getAttribute('class')));
        }

        $tspan = self::firstChildElement($element, 'tspan') ?? $element;
        $tspan->textContent = $value;
    }

    private static function hide(DOMXPath $xpath, string $id): void
    {
        $element = self::element($xpath, $id);

        if (! $element instanceof DOMElement) {
            return;
        }

        $element->setAttribute('display', 'none');

        $tspan = self::firstChildElement($element, 'tspan') ?? $element;
        $tspan->textContent = '';
    }

    private static function replaceFieldLabels(DOMDocument $dom, DOMXPath $xpath): void
    {
        $root = $dom->documentElement;

        if (! $root instanceof DOMElement) {
            return;
        }

        $namespace = 'http://www.w3.org/2000/svg';
        $cover = $dom->createElementNS($namespace, 'rect');
        $cover->setAttribute('x', '418');
        $cover->setAttribute('y', '154');
        $cover->setAttribute('width', '172');
        $cover->setAttribute('height', '230');
        $cover->setAttribute('fill', '#ffffff');
        $root->appendChild($cover);

        foreach (self::FIELD_LABELS as $label) {
            $text = $dom->createElementNS($namespace, 'text');
            $text->setAttribute('class', 'cls-8');
            $text->setAttribute('fill', '#664d15');
            $text->setAttribute('direction', 'rtl');
            $text->setAttribute('unicode-bidi', 'isolate');
            $text->setAttribute('text-anchor', 'start');
            $text->setAttribute('transform', 'translate(572 '.$label['y'].')');
            $text->setAttribute('font-size', '24px');

            $tspan = $dom->createElementNS($namespace, 'tspan');
            $tspan->setAttribute('x', '0');
            $tspan->setAttribute('y', '0');
            $tspan->textContent = $label['text'];
            $text->appendChild($tspan);
            $root->appendChild($text);
        }

        foreach (['card-number', 'card-name', 'card-dob', 'card-job'] as $id) {
            $element = self::element($xpath, $id);

            if ($element instanceof DOMElement) {
                $root->appendChild($element);
            }
        }
    }

    private static function setPhoto(DOMXPath $xpath, ?string $src): void
    {
        $element = self::element($xpath, 'card-photo');

        if (! $element instanceof DOMElement) {
            return;
        }

        if (! filled($src)) {
            $element->setAttribute('display', 'none');

            return;
        }

        $element->removeAttribute('display');
        $element->setAttribute('href', $src);
        $element->setAttribute('xlink:href', $src);
    }

    private static function setBarcode(DOMDocument $dom, DOMXPath $xpath, ?string $barcodeSvg): void
    {
        $group = self::element($xpath, 'card-barcode');

        if (! $group instanceof DOMElement || ! filled($barcodeSvg)) {
            return;
        }

        $barcode = new DOMDocument;
        $loaded = $barcode->loadXML($barcodeSvg);

        if ($loaded === false || ! $barcode->documentElement instanceof DOMElement) {
            return;
        }

        $imported = $dom->importNode($barcode->documentElement, true);

        if (! $imported instanceof DOMElement) {
            return;
        }

        $imported->setAttribute('x', '57.25');
        $imported->setAttribute('y', '402.86');
        $imported->setAttribute('width', '490.58');
        $imported->setAttribute('height', '183.05');
        $imported->setAttribute('preserveAspectRatio', 'none');
        $group->appendChild($imported);
    }

    private static function embedFont(DOMXPath $xpath, string $fontSrc): void
    {
        $style = $xpath->query('//svg:style')->item(0);

        if (! $style instanceof DOMElement) {
            return;
        }

        $style->textContent = "@font-face { font-family: SomarSans-SemiBold; src: url('{$fontSrc}') format('truetype'); font-weight: 600; } "
            ."@font-face { font-family: 'Somar Sans'; src: url('{$fontSrc}') format('truetype'); font-weight: 600; } "
            .$style->textContent;
    }

    private static function uniquifyMarkup(string $svg, string $personKey): string
    {
        $prefix = preg_replace('/[^A-Za-z0-9_-]/', '', $personKey) ?: 'card';

        foreach ([
            'clippath-2',
            'clippath-1',
            'clippath',
            'path-3-inside-1_31_751',
            'card-barcode',
            'card-number',
            'card-issued',
            'card-blood',
            'card-photo',
            'card-name',
            'card-job',
            'card-dob',
            'Layer_1',
            'mask',
        ] as $id) {
            $svg = str_replace('id="'.$id.'"', 'id="'.$prefix.'-'.$id.'"', $svg);
            $svg = str_replace('url(#'.$id.')', 'url(#'.$prefix.'-'.$id.')', $svg);
        }

        return (string) preg_replace('/\bcls-(\d+)\b/', $prefix.'-cls-$1', $svg);
    }

    private static function element(DOMXPath $xpath, string $id): ?DOMElement
    {
        $node = $xpath->query('//*[@id="'.$id.'"]')->item(0);

        return $node instanceof DOMElement ? $node : null;
    }

    private static function firstChildElement(DOMElement $element, string $localName): ?DOMElement
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $localName) {
                return $child;
            }
        }

        return null;
    }
}
