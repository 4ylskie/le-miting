@props(['tables', 'plan'])

<p id="floor-plan-guidance" class="lm-meta lm-muted">Kampung-inspired conceptual restaurant floor plan — not a measured plan of the actual venue. Only tables available for your whole visit and party size are shown. Use the radio controls to select a table; scroll the plan sideways on smaller screens.</p>
<div class="relative max-w-full min-w-0 overflow-x-auto border border-line" role="region" aria-label="Table selection floor plan" tabindex="0" aria-describedby="floor-plan-guidance">
    <div class="min-w-[840px] bg-bone p-5">
        @foreach (['Veranda', 'Main dining'] as $zone)
            <section class="border-b border-line py-5" aria-label="{{ $zone }}">
                <h3 class="lm-eyebrow mb-5 text-center">{{ $zone === 'Veranda' ? 'Semi-outdoor veranda' : 'Main dining area' }}</h3>
                <div class="lm-floor-grid grid grid-cols-4 gap-x-5 gap-y-10">
                    @foreach ($tables as $table)
                        @php
                            $position = $plan['positions'][$table->id];
                        @endphp
                        @if ($position['zone'] === $zone)
                            @php
                                $topSeats = (int) ceil($table->capacity / 2);
                                $bottomSeats = (int) floor($table->capacity / 2);
                            @endphp
                            <label data-floor-table="{{ $table->id }}" data-row="{{ $position['row'] }}" data-column="{{ $position['column'] }}" data-capacity="{{ $table->capacity }}" class="lm-floor-table">
                                <input type="radio" name="table_id" value="{{ $table->id }}" class="sr-only" required @checked(old('table_id') == $table->id)
                                    aria-label="Table {{ $table->table_number }}, {{ $table->capacity }} seats, {{ $zone }}"
                                    @if ($errors->has('table_id')) aria-invalid="true" aria-describedby="table_id-error" @endif>
                                <span class="lm-floor-table__drawing" aria-hidden="true">
                                    <span class="lm-floor-table__chairs lm-floor-table__chairs--top">
                                        @for ($seat = 0; $seat < $topSeats; $seat++)
                                            <span class="lm-floor-table__chair" data-chair></span>
                                        @endfor
                                    </span>
                                    <span class="lm-floor-table__top">{{ $table->table_number }}</span>
                                    <span class="lm-floor-table__chairs lm-floor-table__chairs--bottom">
                                        @for ($seat = 0; $seat < $bottomSeats; $seat++)
                                            <span class="lm-floor-table__chair" data-chair></span>
                                        @endfor
                                    </span>
                                </span>
                                <span class="lm-meta">Seats up to {{ $table->capacity }}</span>
                                <span class="lm-floor-table__selected lm-meta" aria-hidden="true">Selected</span>
                            </label>
                        @endif
                    @endforeach
                </div>
                @if ($zone === 'Main dining')
                    <p class="lm-meta lm-muted mt-6 text-center">Circulation / service path</p>
                @endif
            </section>
        @endforeach
        <div class="grid grid-cols-2 divide-x divide-line border-b border-line py-5 text-center lm-meta"><span>Kitchen / service</span><span>Cashier / host</span></div>
        <p class="lm-eyebrow pt-5 text-center">Entrance</p>
    </div>
</div>
