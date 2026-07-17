<section class="rounded-2xl border border-[#fecaca] bg-[#fef2f2] p-5">
    <h2 class="mb-2 text-[11px] font-bold uppercase tracking-wider text-[#dc2626]">Danger Zone</h2>
    <p class="mb-3 text-[11px] text-[#64748b]">Removing a contact cannot be undone.</p>
    <form action="{{ route('directory.destroy', $contact->id) }}" method="POST"
          x-data="{ confirmDelete: false }"
          x-on:submit="if (!confirmDelete) { $event.preventDefault(); confirmDelete = true; }">
        @csrf
        @method('DELETE')
        <input type="hidden" name="return_filters" value="{{ json_encode(session('directory.filters', [])) }}">
        <button type="submit"
                class="w-full rounded-xl border px-4 py-2.5 text-[12px] font-semibold transition focus:outline-none focus:ring-2 focus:ring-red-500/20"
                x-bind:class="confirmDelete ? 'border-[#dc2626] bg-[#dc2626] text-white' : 'border-[#fecaca] bg-white text-[#dc2626] hover:bg-[#fee2e2]'"
                x-text="confirmDelete ? 'Confirm delete' : 'Delete contact'">
            Delete contact
        </button>
    </form>
</section>
