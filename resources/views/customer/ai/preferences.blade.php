<form method="POST" action="{{ route('ai.recommendations.recommend') }}" class="lm-divider lm-stack pt-(--lm-space-lg)" id="rasa-ai-form">
    @csrf
    @php
        $allergenErrorKey = collect($errors->keys())->first(fn ($key) => str_starts_with($key, 'excluded_allergens.'));
        $allergenError = $errors->first('excluded_allergens') ?: ($allergenErrorKey ? $errors->first($allergenErrorKey) : null);
    @endphp
    <div class="lm-field">
        <label for="preferences_text" class="lm-label">What would you like to eat? (optional)</label>
        <textarea id="preferences_text" name="preferences_text" rows="3" maxlength="300" class="lm-control" aria-describedby="preferences_text-help{{ $errors->has('preferences_text') ? ' preferences_text-error' : '' }}" @if ($errors->has('preferences_text')) aria-invalid="true" @endif>{{ old('preferences_text', $preferences['preferences_text'] ?? '') }}</textarea>
        <p id="preferences_text-help" class="lm-meta lm-muted">Leave blank to use allergen selections alone, or try “Chicken under RM20”, “Something spicy”, or “Not too spicy”. Prices are per dish.</p>
        @error('preferences_text')
            <p id="preferences_text-error" class="lm-error" role="alert">{{ $message }}</p>
        @enderror
    </div>
    <fieldset class="lm-field lm-divider pt-(--lm-space-lg)" aria-describedby="excluded_allergens-help{{ $allergenError ? ' excluded_allergens-error' : '' }}">
        <legend class="lm-label">Allergens to exclude</legend>
        <p id="excluded_allergens-help" class="lm-meta lm-muted">Selections use administrator-reviewed recipe and cross-contact records. Tell restaurant staff about your needs before ordering.</p>
        <div class="lm-allergen-grid">
            @php($checkedAllergens = old('excluded_allergens', $selectedAllergens ?? []))
            @foreach ($allergens as $allergen)
                <label for="excluded-allergen-{{ $allergen->code }}" class="lm-choice lm-allergen-option">
                    <input id="excluded-allergen-{{ $allergen->code }}" type="checkbox" name="excluded_allergens[]" value="{{ $allergen->code }}" class="lm-check shrink-0" aria-describedby="excluded_allergens-help{{ $allergenError ? ' excluded_allergens-error' : '' }}" @checked(in_array($allergen->code, is_array($checkedAllergens) ? $checkedAllergens : [], true)) @if ($allergenError) aria-invalid="true" @endif>
                    <span>{{ $allergen->name }}</span>
                </label>
            @endforeach
        </div>
        @if ($allergenError)
            <p id="excluded_allergens-error" class="lm-error" role="alert">{{ $allergenError }}</p>
        @endif
    </fieldset>
    <button type="submit" id="rasa-ai-submit" aria-busy="false" class="lm-button w-full sm:w-auto sm:self-start">Get Rasa AI Recommendations</button>
    <p class="lm-meta lm-muted">Want to browse instead? <a href="{{ route('menu.index') }}" class="lm-button lm-button--quiet">View the full menu</a></p>
    <p class="lm-meta lm-muted">Recommendations are based on menu items that match your preferences.</p>
</form>
