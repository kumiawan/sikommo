<?php

use App\Models\Contact;
use App\Models\Visit;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Kunjungan')] class extends Component {
    use WithPagination;

    // Ambang batas (hari)
    public ?int $activeDays = 30;
    public ?int $inactiveDays = 90;

    // Form catat kunjungan
    public ?int $contactId = null;
    public string $contactName = '';
    public string $visitDate = '';
    public string $visitNote = '';

    public function updatedActiveDays(): void { $this->resetPage(); }
    public function updatedInactiveDays(): void { $this->resetPage(); }

    #[Computed]
    public function activeLimit(): int
    {
        return max(1, $this->activeDays ?? 30);
    }

    #[Computed]
    public function inactiveLimit(): int
    {
        // batas tidak aktif harus selalu lebih besar dari batas aktif
        return max($this->activeLimit + 1, $this->inactiveDays ?? 90);
    }

    #[Computed]
    public function contacts()
    {
        return Contact::query()
            ->withCount('visits')
            ->whereIn('status', ['Pelanggan', 'Tidak Aktif'])
            ->whereNotNull('last_visit_at')
            ->where('last_visit_at', '<=', now()->subDays($this->activeLimit))
            ->orderBy('last_visit_at') // paling lama tidak datang di atas
            ->paginate(10);
    }

    public function openVisitModal(int $id): void
    {
        $contact = Contact::findOrFail($id);

        $this->contactId = $contact->id;
        $this->contactName = $contact->name;
        $this->visitDate = now()->toDateString();
        $this->visitNote = '';

        $this->resetValidation();
        Flux::modal('visit-form')->show();
    }

    public function saveVisit(): void
    {
        $data = $this->validate([
            'visitDate' => ['required', 'date', 'before_or_equal:today'],
            'visitNote' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($data) {
            $contact = Contact::lockForUpdate()->findOrFail($this->contactId);

            Visit::create([
                'contact_id' => $contact->id,
                'user_id'    => auth()->id(),
                'visited_at' => $data['visitDate'],
                'note'       => $data['visitNote'] ?: null,
            ]);

            // Perbarui "terakhir datang" hanya jika tanggalnya lebih baru
            if (! $contact->last_visit_at || $contact->last_visit_at->lt($data['visitDate'])) {
                $contact->update(['last_visit_at' => $data['visitDate']]);
            }
        });

        Flux::modal('visit-form')->close();
        Flux::toast('Kunjungan berhasil dicatat.');
    }
};
?>

<div>
    <flux:heading size="xl" level="1" class="mb-6">Pelacakan kunjungan terakhir</flux:heading>

    <div class="space-y-4">
        {{-- Ambang batas --}}
        <flux:card>
            <div class="flex flex-wrap items-center gap-3">
                <flux:text>Ambang batas: Aktif &lt;</flux:text>
                <flux:input
                    type="number"
                    min="1"
                    class="w-28!"
                    wire:model.live.debounce.500ms="activeDays"
                />
                <flux:text>hari · Tidak aktif &gt;</flux:text>
                <flux:input
                    type="number"
                    min="2"
                    class="w-28!"
                    wire:model.live.debounce.500ms="inactiveDays"
                />
                <flux:text>hari</flux:text>
            </div>
        </flux:card>

        {{-- Daftar pelanggan --}}
        <flux:card class="space-y-4">
            <flux:heading size="lg">
                Pelanggan yang lama tidak datang ({{ $this->contacts->total() }})
            </flux:heading>

            <flux:table :paginate="$this->contacts">
                <flux:table.columns>
                    <flux:table.column>Nama</flux:table.column>
                    <flux:table.column>Terakhir datang</flux:table.column>
                    <flux:table.column>Total kunjungan</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column align="end"></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->contacts as $contact)
                        @php
                            $days = $contact->lastVisitDays();
                            $isInactive = $days > $this->inactiveLimit;
                            $waNumber = preg_replace('/\D/', '', $contact->phone);
                        @endphp

                        <flux:table.row :key="$contact->id">
                            <flux:table.cell variant="strong">{{ $contact->name }}</flux:table.cell>

                            <flux:table.cell>{{ $days }} hari lalu</flux:table.cell>

                            <flux:table.cell>{{ $contact->visits_count }}</flux:table.cell>

                            <flux:table.cell>
                                <flux:badge size="sm" :color="$isInactive ? 'red' : 'amber'" inset="top bottom">
                                    {{ $isInactive ? 'Tidak aktif' : 'Hampir hilang' }}
                                </flux:badge>
                            </flux:table.cell>

                            <flux:table.cell align="end">
                                <div class="flex items-center justify-end gap-2">
                                    <flux:button
                                        size="sm"
                                        icon="chat-bubble-left"
                                        inset="top bottom"
                                        href="https://wa.me/{{ $waNumber }}"
                                        target="_blank"
                                    >
                                        Chat
                                    </flux:button>

                                    <flux:button
                                        size="sm"
                                        icon="check"
                                        inset="top bottom"
                                        wire:click="openVisitModal({{ $contact->id }})"
                                    >
                                        Catat kunjungan
                                    </flux:button>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5" class="py-8 text-center text-zinc-500">
                                Tidak ada pelanggan yang melewati ambang batas.
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    </div>

    {{-- Modal catat kunjungan --}}
    <flux:modal name="visit-form" class="md:w-[26rem]">
        <form wire:submit="saveVisit" class="space-y-5">
            <div>
                <flux:heading size="lg">Catat kunjungan</flux:heading>
                <flux:text class="mt-1">{{ $contactName }}</flux:text>
            </div>

            <flux:input type="date" wire:model="visitDate" label="Tanggal kunjungan" :max="now()->toDateString()" />

            <flux:textarea wire:model="visitNote" label="Catatan (opsional)" rows="3" placeholder="Misal: beli paket A, minta follow up..." />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
