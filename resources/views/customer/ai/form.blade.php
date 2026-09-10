<form method="POST" action="{{ route('ai.recommendations.recommend') }}" class="space-y-6 rounded-2xl border border-brand-100 bg-white p-6 shadow-sm md:p-8" id="rasa-ai-form">
    @csrf

    <div>
        <label for="category" class="block text-sm font-medium text-text">What are you hungry for?</label>
        <select
            id="category"
            name="category"
            class="mt-1 w-full rounded-md border border-brand-200 bg-white px-4 py-2.5 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary"
        >
            <option value="">Any category</option>
            @foreach ($categories as $category)
                <option value="{{ $category->slug }}" @selected(old('category') === $category->slug)>{{ $category->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
        <div>
            <label for="spice_level" class="block text-sm font-medium text-text">Spice level</label>
            <select
                id="spice_level"
                name="spice_level"
                class="mt-1 w-full rounded-md border border-brand-200 bg-white px-4 py-2.5 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary"
            >
                <option value="">No preference</option>
                @foreach ($spiceLevels as $level)
                    <option value="{{ $level }}" @selected(old('spice_level') === $level)>{{ $level }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="protein_type" class="block text-sm font-medium text-text">Protein</label>
            <select
                id="protein_type"
                name="protein_type"
                class="mt-1 w-full rounded-md border border-brand-200 bg-white px-4 py-2.5 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary"
            >
                <option value="">No preference</option>
                @foreach ($proteinTypes as $protein)
                    <option value="{{ $protein }}" @selected(old('protein_type') === $protein)>{{ $protein }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="dietary_type" class="block text-sm font-medium text-text">Dietary preference</label>
            <select
                id="dietary_type"
                name="dietary_type"
                class="mt-1 w-full rounded-md border border-brand-200 bg-white px-4 py-2.5 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary"
            >
                <option value="">No preference</option>
                @foreach ($dietaryTypes as $dietary)
                    <option value="{{ $dietary }}" @selected(old('dietary_type') === $dietary)>{{ $dietary }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="flavor_profile" class="block text-sm font-medium text-text">Flavour profile</label>
            <select
                id="flavor_profile"
                name="flavor_profile"
                class="mt-1 w-full rounded-md border border-brand-200 bg-white px-4 py-2.5 text-sm text-text focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary"
            >
                <option value="">No preference</option>
                @foreach ($flavorProfiles as $flavor)
                    <option value="{{ $flavor }}" @selected(old('flavor_profile') === $flavor)>{{ $flavor }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <button
        type="submit"
        id="rasa-ai-submit"
        aria-busy="false"
        class="inline-flex w-full items-center justify-center rounded-md bg-accent px-6 py-3 text-base font-semibold text-white shadow-sm hover:bg-accent-light focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2"
    >
        Get Rasa AI Recommendations
    </button>

    <p class="text-center text-sm text-text-muted">
        Want to browse instead? <a href="{{ route('menu.index') }}" class="font-semibold text-brand-700 hover:text-brand-800">View the full menu</a>.
    </p>
</form>

<script>
    (function () {
        var form = document.getElementById('rasa-ai-form');
        var button = document.getElementById('rasa-ai-submit');

        if (!form || !button) {
            return;
        }

        form.addEventListener('submit', function () {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            button.dataset.originalLabel = button.textContent.trim();
            button.textContent = 'Generating recommendations\u2026';
            button.classList.add('cursor-not-allowed', 'opacity-60');
        });
    })();
</script>