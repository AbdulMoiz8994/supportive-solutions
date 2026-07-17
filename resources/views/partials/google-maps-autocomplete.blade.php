{{--
    Google Places (New) address autocomplete.

    Include this partial on any page that has an address input. Mark the address
    input with the `data-gmaps` attribute. As the user types we query the
    Places API (New) AutocompleteSuggestion service and render our own dropdown,
    so the existing styled <input> is preserved. On selection it fills, within
    the SAME <form>, any sibling fields found by name:
        - the marked input itself  → full formatted address
        - [name="county"]          → county (" County" stripped)
        - [name="city"]            → locality
        - [name="state"]           → state (2-letter)
        - [name="zip_code"] / [name="zip"] / [name="postal_code"] → ZIP

    Works with Alpine x-model (dispatches input/change) and with edit panels
    that mount fields lazily via x-if/x-show (uses focus delegation).

    Requires "Maps JavaScript API" + "Places API (New)" enabled on the key.
    Loads only when GOOGLE_MAPS_API_KEY is configured.
--}}
@php($gmapsKey = config('services.google_maps.key'))
@if($gmapsKey)
@once
@push('scripts')
<style>
    .gmaps-ac {
        position: absolute; z-index: 9999; background: #fff;
        border: 1px solid #e2e8f0; border-radius: 10px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, .12);
        overflow: hidden; font-size: 13px; max-height: 280px; overflow-y: auto;
    }
    .gmaps-ac-item { padding: 9px 12px; cursor: pointer; color: #0f172a; line-height: 1.3; }
    .gmaps-ac-item:hover, .gmaps-ac-item.is-active { background: #eff4ff; }
    .gmaps-ac-item .sub { display: block; color: #94a3b8; font-size: 11.5px; }
    .gmaps-ac-empty { padding: 9px 12px; color: #94a3b8; }
</style>
<script>
(function () {
    if (window.__gmapsBooted) return;
    window.__gmapsBooted = true;

    var ready = false;
    var pending = [];

    window.__gmapsReady = function () { ready = true; pending.splice(0).forEach(request); };

    function loadApi() {
        if (window.__gmapsLoading) return;
        window.__gmapsLoading = true;
        var s = document.createElement('script');
        s.src = 'https://maps.googleapis.com/maps/api/js?key={{ $gmapsKey }}&libraries=places&loading=async&callback=__gmapsReady';
        s.async = true; s.defer = true;
        s.onerror = function () { console.error('Google Maps failed to load — check the API key & billing.'); };
        document.head.appendChild(s);
    }

    function debounce(fn, ms) {
        var t; return function () { var a = arguments, c = this; clearTimeout(t); t = setTimeout(function () { fn.apply(c, a); }, ms); };
    }

    function dispatch(el) {
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function setField(form, names, value) {
        if (value == null || value === '') return;
        for (var i = 0; i < names.length; i++) {
            var el = form.querySelector('[name="' + names[i] + '"]');
            if (!el) continue;
            if (el.tagName === 'SELECT') {
                var matched = false;
                for (var o = 0; o < el.options.length; o++) {
                    if (el.options[o].value.toLowerCase() === String(value).toLowerCase()) { el.value = el.options[o].value; matched = true; break; }
                }
                if (!matched) continue;
                el.dispatchEvent(new Event('change', { bubbles: true }));
            } else {
                el.value = value; dispatch(el);
            }
            return; // first matching field name wins
        }
    }

    function parse(components) {
        var out = { city: '', state: '', zip: '', county: '' };
        (components || []).forEach(function (c) {
            var t = c.types || [];
            if (t.indexOf('locality') > -1) out.city = c.longText;
            else if (!out.city && t.indexOf('postal_town') > -1) out.city = c.longText;
            else if (t.indexOf('administrative_area_level_1') > -1) out.state = c.shortText;
            else if (t.indexOf('postal_code') > -1) out.zip = c.longText;
            else if (t.indexOf('administrative_area_level_2') > -1) out.county = (c.longText || '').replace(/\s+County$/i, '');
        });
        return out;
    }

    async function setup(input) {
        if (input.__gmapsAttached) return;
        input.__gmapsAttached = true;
        input.setAttribute('autocomplete', 'off');

        var lib = await google.maps.importLibrary('places');
        var AutocompleteSuggestion = lib.AutocompleteSuggestion;
        var AutocompleteSessionToken = lib.AutocompleteSessionToken;

        var token = new AutocompleteSessionToken();
        var box = null, items = [], active = -1, suggestions = [];

        function close() { if (box) { box.remove(); box = null; } items = []; active = -1; }

        function place() {
            var r = input.getBoundingClientRect();
            box.style.left = (window.scrollX + r.left) + 'px';
            box.style.top = (window.scrollY + r.bottom + 4) + 'px';
            box.style.width = r.width + 'px';
        }

        function render() {
            close();
            box = document.createElement('div');
            box.className = 'gmaps-ac';
            if (!suggestions.length) {
                var e = document.createElement('div'); e.className = 'gmaps-ac-empty'; e.textContent = 'No matches';
                box.appendChild(e);
            }
            suggestions.forEach(function (s, idx) {
                var p = s.placePrediction;
                var el = document.createElement('div');
                el.className = 'gmaps-ac-item';
                var main = (p.mainText && p.mainText.text) || (p.text && p.text.text) || '';
                var sub = (p.secondaryText && p.secondaryText.text) || '';
                el.innerHTML = '<span>' + main + '</span>' + (sub ? '<span class="sub">' + sub + '</span>' : '');
                el.addEventListener('mousedown', function (ev) { ev.preventDefault(); choose(idx); });
                box.appendChild(el);
                items.push(el);
            });
            document.body.appendChild(box);
            place();
        }

        function highlight(i) {
            items.forEach(function (el) { el.classList.remove('is-active'); });
            active = i;
            if (items[i]) { items[i].classList.add('is-active'); items[i].scrollIntoView({ block: 'nearest' }); }
        }

        async function choose(i) {
            var s = suggestions[i]; if (!s || !s.placePrediction) return;
            var pl = s.placePrediction.toPlace();
            await pl.fetchFields({ fields: ['formattedAddress', 'addressComponents'] });
            var parts = parse(pl.addressComponents);
            input.value = pl.formattedAddress || input.value;
            dispatch(input);
            var form = input.closest('form');
            if (form) {
                setField(form, ['county'], parts.county);
                setField(form, ['city'], parts.city);
                setField(form, ['state'], parts.state);
                setField(form, ['zip_code', 'zip', 'postal_code'], parts.zip);
            }
            token = new AutocompleteSessionToken(); // new billing session after a pick
            close();
        }

        var onInput = debounce(async function () {
            var text = input.value.trim();
            if (text.length < 3) { close(); return; }
            try {
                var res = await AutocompleteSuggestion.fetchAutocompleteSuggestions({
                    input: text, sessionToken: token, includedRegionCodes: ['us'],
                });
                suggestions = (res.suggestions || []).filter(function (s) { return s.placePrediction; });
                render();
            } catch (err) {
                console.error('Google Places (New) autocomplete error:', err && err.message ? err.message : err);
                close();
            }
        }, 250);

        input.addEventListener('input', onInput);
        input.addEventListener('keydown', function (e) {
            if (!box) return;
            if (e.key === 'ArrowDown') { e.preventDefault(); highlight(Math.min(active + 1, items.length - 1)); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); highlight(Math.max(active - 1, 0)); }
            else if (e.key === 'Enter') { if (active > -1) { e.preventDefault(); choose(active); } }
            else if (e.key === 'Escape') { close(); }
        });
        input.addEventListener('blur', function () { setTimeout(close, 150); });
    }

    function request(input) {
        if (input.__gmapsAttached) return;
        if (ready) setup(input);
        else if (pending.indexOf(input) === -1) pending.push(input);
    }

    document.addEventListener('focusin', function (e) {
        var el = e.target;
        if (el && el.matches && el.matches('input[data-gmaps]')) request(el);
    });

    function scan() { document.querySelectorAll('input[data-gmaps]').forEach(request); }
    if (document.readyState !== 'loading') scan();
    else document.addEventListener('DOMContentLoaded', scan);

    loadApi();
})();
</script>
@endpush
@endonce
@endif
