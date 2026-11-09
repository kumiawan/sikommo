<?php

use App\Models\Contact;
use App\Models\User;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Kontak')] class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';
    #[Url]
    public string $tag = '';
    #[Url]
    public string $status = '';

    public string $sortBy = 'name';
    public string $sortDirection = 'asc';

    // Form
    public ?int $contactId = null;
    public string $name = '';
    public string $phone = '';
    public string $formTag = '';
    public string $formStatus = 'Lead';
    public ?int $agentId = null;

    public ?int $deleteId = null;

    public array $tags = ['VIP', 'Baru', 'Prospek'];
    public array $statuses = ['Lead', 'Prospek', 'Pelanggan', 'Tidak Aktif'];

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedTag(): void { $this->resetPage(); }
    public function updatedStatus(): void { $this->resetPage(); }

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    #[Computed]
    public function contacts()
    {
        return Contact::query()
            ->with('agent')
            ->when($this->search, fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('phone', 'like', "%{$this->search}%")))
            ->when($this->tag, fn ($q) => $q->where('tag', $this->tag))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(10);
    }

    #[Computed]
    public function agents()
    {
        return User::orderBy('name')->get(['id', 'name']);
    }

    public function create(): void
    {
        $this->reset('contactId', 'name', 'phone', 'formTag', 'agentId');
        $this->formStatus = 'Lead';
        $this->resetValidation();
        Flux::modal('contact-form')->show();
    }

    public function edit(int $id): void
    {
        $contact = Contact::findOrFail($id);

        $this->contactId = $contact->id;
        $this->name = $contact->name;
        $this->phone = $contact->phone;
        $this->formTag = $contact->tag ?? '';
        $this->formStatus = $contact->status;
        $this->agentId = $contact->agent_id;

        $this->resetValidation();
        Flux::modal('contact-form')->show();
    }

    public function save(): void
    {
        $data = $this->validate([
            'name'       => ['required', 'string', 'max:255'],
            'phone'      => ['required', 'string', 'max:20', 'unique:contacts,phone,' . $this->contactId],
            'formTag'    => ['nullable', 'string'],
            'formStatus' => ['required', 'string'],
            'agentId'    => ['nullable', 'exists:users,id'],
        ]);

        Contact::updateOrCreate(['id' => $this->contactId], [
            'name'     => $data['name'],
            'phone'    => $data['phone'],
            'tag'      => $data['formTag'] ?: null,
            'status'   => $data['formStatus'],
            'agent_id' => $data['agentId'],
        ]);

        Flux::modal('contact-form')->close();
        Flux::toast('Kontak berhasil disimpan.');
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteId = $id;
        Flux::modal('delete-contact')->show();
    }

    public function delete(): void
    {
        Contact::whereKey($this->deleteId)->delete();
        $this->deleteId = null;

        Flux::modal('delete-contact')->close();
        Flux::toast('Kontak dihapus.');
    }
};
?>

<div>
    <flux:heading size="xl" level="1" class="mb-6">Kontak</flux:heading>

    <flux:card class="space-y-4">
        {{-- Toolbar --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <flux:input
                    class="sm:w-64"
                    icon="magnifying-glass"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari nama / nomor..."
                    clearable
                />

                <flux:select wire:model.live="tag" class="sm:w-40">
                    <flux:select.option value="">Semua tag</flux:select.option>
                    @foreach ($tags as $t)
                        <flux:select.option value="{{ $t }}">{{ $t }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="status" class="sm:w-44">
                    <flux:select.option value="">Semua status</flux:select.option>
                    @foreach ($statuses as $s)
                        <flux:select.option value="{{ $s }}">{{ $s }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="flex items-center gap-2">
                <flux:button icon="arrow-down-tray">Export</flux:button>
                <flux:button icon="arrow-up-tray">Import CSV/Excel</flux:button>
                <flux:button variant="primary" icon="plus" wire:click="create">Tambah</flux:button>
            </div>
        </div>

        {{-- Tabel --}}
        <flux:table :paginate="$this->contacts">
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">Nama</flux:table.column>
                <flux:table.column>No. WA</flux:table.column>
                <flux:table.column>Tag</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')">Status</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'last_visit_at'" :direction="$sortDirection" wire:click="sort('last_visit_at')">Terakhir datang</flux:table.column>
                <flux:table.column>Agent</flux:table.column>
                <flux:table.column align="end"></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->contacts as $contact)
                    <flux:table.row :key="$contact->id">
                        <flux:table.cell variant="strong">
                            <flux:link href="#" class="font-semibold">{{ $contact->name }}</flux:link>
                        </flux:table.cell>

                        <flux:table.cell>{{ $contact->phone }}</flux:table.cell>

                        <flux:table.cell>
                            @if ($contact->tag)
                                <flux:badge size="sm" inset="top bottom">{{ $contact->tag }}</flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>{{ $contact->status }}</flux:table.cell>

                        <flux:table.cell>
                            @if ($contact->last_visit_at)
                                <flux:badge size="sm" :color="$contact->lastVisitColor()" inset="top bottom">
                                    {{ $contact->lastVisitDays() }} hari lalu
                                </flux:badge>
                            @else
                                <span class="text-zinc-400">-</span>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>{{ $contact->agent?->name ?? '-' }}</flux:table.cell>

                        <flux:table.cell align="end">
                            <div class="flex items-center justify-end gap-1">
                                <flux:button size="sm" variant="ghost" icon="chat-bubble-left" inset="top bottom" href="#" />
                                <flux:button size="sm" variant="ghost" icon="pencil-square" inset="top bottom" wire:click="edit({{ $contact->id }})" />
                                <flux:button size="sm" variant="ghost" icon="trash" inset="top bottom" class="text-red-500!" wire:click="confirmDelete({{ $contact->id }})" />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-8 text-center text-zinc-500">
                            Belum ada kontak.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    {{-- Modal Tambah / Edit --}}
    <flux:modal name="contact-form" class="md:w-[28rem]">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $contactId ? 'Edit Kontak' : 'Tambah Kontak' }}</flux:heading>

            <flux:input wire:model="name" label="Nama" placeholder="Nama lengkap" />
            <flux:input wire:model="phone" label="No. WA" placeholder="+62812..." />

            <div class="grid grid-cols-2 gap-4">
                <flux:select wire:model="formTag" label="Tag">
                    <flux:select.option value="">Tanpa tag</flux:select.option>
                    @foreach ($tags as $t)
                        <flux:select.option value="{{ $t }}">{{ $t }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="formStatus" label="Status">
                    @foreach ($statuses as $s)
                        <flux:select.option value="{{ $s }}">{{ $s }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <flux:select wire:model="agentId" label="Agent">
                <flux:select.option value="">Belum ditentukan</flux:select.option>
                @foreach ($this->agents as $agent)
                    <flux:select.option value="{{ $agent->id }}">{{ $agent->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Modal Hapus --}}
    <flux:modal name="delete-contact" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Hapus kontak?</flux:heading>
                <flux:text class="mt-2">Data kontak ini akan dihapus dan tidak bisa dikembalikan.</flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button variant="danger" wire:click="delete">Hapus</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
