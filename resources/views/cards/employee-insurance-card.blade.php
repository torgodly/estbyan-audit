@php
    /** @var \Illuminate\Support\Collection<int, \App\Support\EmployeeInsuranceCard> $cards */
    $cards = isset($cards) ? $cards : collect([$card]);
    $embedAssets = $embedAssets ?? true;
    $preview = $preview ?? false;
    $printPack = $printPack ?? false;
    $cardActions = $cardActions ?? false;
    $fontSrc = $embedAssets
        ? $cards->first()?->fontDataUri
        : $cards->first()?->fontUrl;
@endphp
<div @class(['employee-insurance-cards', 'employee-insurance-cards--preview' => $preview, 'employee-insurance-cards--print' => $printPack]) dir="ltr" lang="ar">
    <style>
        @font-face {
            font-family: 'Somar Sans';
            src: url('{{ $fontSrc }}') format('truetype');
            font-weight: 600;
            font-style: normal;
        }

        @font-face {
            font-family: SomarSans-SemiBold;
            src: url('{{ $fontSrc }}') format('truetype');
            font-weight: 600;
            font-style: normal;
        }

        .employee-insurance-cards {
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            gap: 24px;
            padding: 0;
            margin: 0;
            background: #ffffff;
        }

        .employee-insurance-cards *,
        .employee-insurance-cards *::before,
        .employee-insurance-cards *::after {
            box-sizing: border-box;
        }

        .employee-id-card {
            position: relative;
            width: 1004px;
            height: 634px;
            margin: 0;
            padding: 0;
            background: #ffffff;
            overflow: hidden;
            flex: none;
        }

        .employee-id-card__canvas {
            position: absolute;
            inset: 0;
            margin: auto;
        }

        .employee-id-card--front .employee-id-card__canvas {
            width: 972.22px;
            height: 601.8px;
        }

        .employee-id-card--back .employee-id-card__canvas {
            width: 1004px;
            height: 634px;
        }

        .employee-id-card__art,
        .employee-id-card__canvas > svg {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: fill;
        }

        .employee-id-card__photo {
            position: absolute;
            left: 595.49px;
            top: 173.46px;
            width: 303.59px;
            height: 368.18px;
            object-fit: cover;
            border-radius: 16.12px;
        }
    </style>

    @foreach ($cards as $card)
        @if ($preview)
            <div class="insurance-card-preview-person">
                <div class="insurance-card-preview-person__head">
                    <div>
                        <h4 class="insurance-card-preview-person__title">{{ $card->heading() }}</h4>
                        <p class="insurance-card-preview-person__meta">{{ $card->name }} · {{ $card->jobTitle }}</p>
                    </div>
                    <div class="insurance-card-preview-person__status">
                        @if ($cardActions)
                            <button
                                type="button"
                                class="hr-print-toggle {{ $card->isPrinted ? 'hr-print-toggle--on' : '' }}"
                                wire:click="toggleInsuranceCardPrinted({{ \Illuminate\Support\Js::from($card->personKey) }})"
                                wire:loading.attr="disabled"
                                wire:target="toggleInsuranceCardPrinted"
                                role="switch"
                                aria-checked="{{ $card->isPrinted ? 'true' : 'false' }}"
                            >
                                <span class="hr-print-toggle__switch" aria-hidden="true"></span>
                                <span>{{ $card->printedAtLabel }}</span>
                            </button>
                            <button
                                type="button"
                                class="hr-card-action"
                                x-on:click="exportInsuranceCards('print', {{ \Illuminate\Support\Js::from($card->personKey) }})"
                                x-bind:disabled="insuranceCardsBusy"
                            >
                                طباعة هذه البطاقة
                            </button>
                        @else
                            <span @class(['hr-chip', 'hr-chip--approved' => $card->isPrinted, 'hr-chip--editing' => ! $card->isPrinted])>
                                {{ $card->printedAtLabel }}
                            </span>
                        @endif
                    </div>
                </div>
                <div class="insurance-card-preview-person__faces">
        @endif

        <div @class(['insurance-card-frame' => $preview])>
            <section class="employee-id-card employee-id-card--front" data-card-person="{{ $card->personKey }}">
                <div class="employee-id-card__canvas">
                    @if ($printPack)
                        <img
                            class="employee-id-card__art"
                            src="{{ $card->frontSvgDataUri() }}"
                            alt=""
                            width="972"
                            height="602"
                        >
                        @if ($card->photoDataUri)
                            <img
                                class="employee-id-card__photo"
                                src="{{ $card->photoDataUri }}"
                                alt=""
                                width="304"
                                height="368"
                            >
                        @endif
                    @else
                        {!! $card->frontSvg($embedAssets) !!}
                    @endif
                </div>
            </section>
        </div>

        <div @class(['insurance-card-frame' => $preview])>
            <section class="employee-id-card employee-id-card--back" data-card-person="{{ $card->personKey }}">
                <div class="employee-id-card__canvas">
                    <img
                        class="employee-id-card__art"
                        src="{{ $card->backArtworkUrl }}"
                        alt=""
                        width="1004"
                        height="634"
                    >
                </div>
            </section>
        </div>

        @if ($preview)
                </div>
            </div>
        @endif
    @endforeach
</div>
