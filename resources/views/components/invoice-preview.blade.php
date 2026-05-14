@props(['order'])

@php
    use App\Services\PhotoDamageAnalyzer;

    $LEVEL_PRICES = PhotoDamageAnalyzer::PRICES;
    $LEVEL_PRICES_TTC = PhotoDamageAnalyzer::PRICES_TTC;
    $LEVEL_LABELS = [
        'light'  => 'Restauration Standard',
        'medium' => 'Restauration Avancée',
        'heavy'  => 'Restauration Complète',
    ];
    $LEVEL_DETAIL = [
        'light'  => 'Jaunissement, poussière légère, légères décolorations',
        'medium' => 'Rayures, décoloration forte, grain important, pliures',
        'heavy'  => 'Déchirures, dégâts eau, zones manquantes, moisissures',
    ];

    $activeRetouched = $order->getMedia('retouched')
        ->filter(fn($m) => ! $m->getCustomProperty('is_rejected', false));

    $lineItems = $activeRetouched
        ->groupBy(fn($m) => $m->getCustomProperty('ai_level', $order->damage_level ?? 'light'))
        ->map(fn($photos, $lvl) => [
            'level'       => $lvl,
            'label'       => $LEVEL_LABELS[$lvl] ?? ucfirst($lvl),
            'detail'      => $LEVEL_DETAIL[$lvl] ?? '',
            'count'       => $photos->count(),
            'unit_ht_c'   => $LEVEL_PRICES[$lvl] ?? 83,
            'unit_ttc_c'  => $LEVEL_PRICES_TTC[$lvl] ?? 100,
            'total_ht_c'  => $photos->count() * ($LEVEL_PRICES[$lvl] ?? 83),
            'total_ttc_c' => $photos->count() * ($LEVEL_PRICES_TTC[$lvl] ?? 100),
        ])
        ->sortBy('level')
        ->values();

    if ($lineItems->isEmpty()) {
        $fallbackLevel = $order->damage_level ?? 'light';
        $nPhotos = max(1, $activeRetouched->count() ?: (int) ($order->photo_count ?? 1));
        $lineItems = collect([[
            'level'       => $fallbackLevel,
            'label'       => $LEVEL_LABELS[$fallbackLevel] ?? 'Restauration',
            'detail'      => $LEVEL_DETAIL[$fallbackLevel] ?? '',
            'count'       => $nPhotos,
            'unit_ht_c'   => $LEVEL_PRICES[$fallbackLevel] ?? 83,
            'unit_ttc_c'  => $LEVEL_PRICES_TTC[$fallbackLevel] ?? 100,
            'total_ht_c'  => $nPhotos * ($LEVEL_PRICES[$fallbackLevel] ?? 83),
            'total_ttc_c' => $nPhotos * ($LEVEL_PRICES_TTC[$fallbackLevel] ?? 100),
        ]]);
    }

    $discountC = (int) ($order->discount_cents ?? 0);
    $baseHtC   = (int) $lineItems->sum('total_ht_c');
    $baseTtcC  = (int) $lineItems->sum('total_ttc_c');
    $htNetC    = max(0, $baseHtC - $discountC);
    $ttcC      = max(0, $baseTtcC - $discountC);
    $tvaC      = $ttcC - $htNetC;
    $isFree    = $ttcC === 0;

    $year       = $order->paid_at?->format('Y') ?? now()->format('Y');
    $seq        = str_pad(substr($order->reference, -4), 4, '0', STR_PAD_LEFT);
    $invoiceNum = "FAC-{$year}-{$seq}";
@endphp

<div class="bg-[#F5F1E8] text-[#1A1208] p-6 sm:p-12 shadow-2xl rounded-sm max-w-3xl mx-auto overflow-hidden font-serif selection:bg-[#C9A84C]/30">
    {{-- Header --}}
    <div class="border-t-8 border-[#C9A84C] pt-8 mb-10 flex flex-col sm:flex-row justify-between items-start gap-6">
        <div>
            <img src="{{ asset('images/logo-text-light.png') }}" alt="OmnyRestore" class="h-20 sm:h-28 object-contain">
        </div>
        <div class="text-right">
            <div class="text-[10px] tracking-[0.2em] uppercase text-[#B0A090]">Facture</div>
            <div class="text-xl font-bold mt-1">{{ $invoiceNum }}</div>
            <div class="text-[11px] text-[#9E9085] mt-1">Émise le {{ $order->paid_at?->format('d/m/Y') ?? now()->format('d/m/Y') }}</div>
        </div>
    </div>

    {{-- Parties --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-8 mb-10 border-b border-[#EDE8E0] pb-8">
        <div>
            <div class="text-[9px] tracking-[0.2em] uppercase text-[#B0A090] border-b border-[#EDE8E0] mb-3 pb-1">Prestataire</div>
            <div class="font-bold text-base mb-1">OmnyRestore</div>
            <div class="text-xs text-[#7A6E5E] leading-relaxed">
                contact@omnyrestore.fr<br>
                omnyrestore.fr
            </div>
        </div>
        <div class="sm:text-right">
            <div class="text-[9px] tracking-[0.2em] uppercase text-[#B0A090] border-b border-[#EDE8E0] mb-3 pb-1">Facturé à</div>
            <div class="font-bold text-base mb-1">{{ $order->user->name }}</div>
            <div class="text-xs text-[#7A6E5E] leading-relaxed">
                {{ $order->user->email }}
            </div>
        </div>
    </div>

    {{-- Stamp --}}
    <div class="text-center mb-10">
        <span class="inline-block border-2 border-[#1a7a3f] text-[#1a7a3f] px-6 py-2 text-sm font-bold tracking-[0.2em] uppercase">
            ✓ Payée — {{ $order->paid_at?->format('d/m/Y') ?? now()->format('d/m/Y') }}
        </span>
    </div>

    {{-- Items --}}
    <div class="overflow-x-auto">
        <table class="w-full text-xs mb-8 border-collapse min-w-[500px]">
            <thead>
                <tr class="bg-[#EDE8E0] text-[#9E9085] uppercase tracking-wider text-[9px]">
                    <th class="p-3 text-left">Prestation</th>
                    <th class="p-3 text-center">Qté</th>
                    <th class="p-3 text-right">PU TTC</th>
                    <th class="p-3 text-right">Total TTC</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#EDE8E0]">
                @foreach($lineItems as $item)
                <tr>
                    <td class="p-3">
                        <div class="font-bold text-sm">{{ $item['label'] }}</div>
                        <div class="text-[10px] text-[#9E9085] mt-0.5">{{ $item['detail'] }}</div>
                    </td>
                    <td class="p-3 text-center font-medium">{{ $item['count'] }}</td>
                    <td class="p-3 text-right font-medium">{{ number_format($item['unit_ttc_c'] / 100, 2, ',', ' ') }} €</td>
                    <td class="p-3 text-right font-bold">{{ number_format($item['total_ttc_c'] / 100, 2, ',', ' ') }} €</td>
                </tr>
                @endforeach
                @if($discountC > 0)
                <tr class="bg-[#f0faf5] text-[#1a7a3f]">
                    <td class="p-3 font-bold">Remise ({{ strtoupper($order->coupon_code) }})</td>
                    <td class="p-3">—</td>
                    <td class="p-3">—</td>
                    <td class="p-3 text-right font-bold">-{{ number_format($discountC / 100, 2, ',', ' ') }} €</td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>

    {{-- Totals --}}
    <div class="flex justify-end">
        <table class="w-64 text-sm">
            <tr class="text-[#7A6E5E]">
                <td class="py-1">Total HT net</td>
                <td class="py-1 text-right">{{ number_format($htNetC / 100, 2, ',', ' ') }} €</td>
            </tr>
            <tr class="text-[#B0A090] text-xs">
                <td class="py-1">TVA 20%</td>
                <td class="py-1 text-right">{{ number_format($tvaC / 100, 2, ',', ' ') }} €</td>
            </tr>
            <tr class="border-t-2 border-[#1A1208] pt-3 mt-2">
                <td class="py-3 font-bold text-lg">Total TTC</td>
                <td class="py-3 text-right font-bold text-xl">
                    @if($isFree) OFFERT
                    @else {{ number_format($ttcC / 100, 2, ',', ' ') }} €
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <div class="mt-12 text-[10px] text-[#9E9085] leading-relaxed border-l-4 border-[#C9A84C] pl-4 italic">
        Ceci est une prévisualisation de votre facture officielle. Vous pouvez la télécharger au format PDF pour vos archives comptables.
    </div>
</div>
