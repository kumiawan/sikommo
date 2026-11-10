<?php

use App\Models\AuditLog;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Pengguna & Audit')] class extends Component {
    public string $name = '';
    public string $email = '';
    public string $password = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->role === 'admin', 403);
    }

    #[Computed]
    public function users()
    {
        return User::orderByRaw("role = 'admin' desc")->orderBy('name')->get();
    }

    #[Computed]
    public function logs()
    {
        return AuditLog::with('user')->latest()->limit(10)->get();
    }

    public function create(): void
    {
        $this->reset('name', 'email', 'password');
        $this->resetValidation();
        Flux::modal('agent-form')->show();
    }

    public function save(): void
    {
        abort_unless(auth()->user()->role === 'admin', 403);

        $data = $this->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'role'     => 'agent',
        ]);

        AuditLog::record("Menambah agent {$data['name']}");

        unset($this->users, $this->logs); // segarkan computed property

        Flux::modal('agent-form')->close();
        Flux::toast('Agent berhasil ditambahkan.');
    }
};
?>

<div>
    <flux:heading size="xl" level="1" class="mb-6">Pengguna &amp; Audit log</flux:heading>

    <div class="grid gap-4 lg:grid-cols-2 lg:items-start">
        {{-- Pengguna & role --}}
        <flux:card class="space-y-4">
            <flux:heading size="lg">Pengguna &amp; role</flux:heading>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Nama</flux:table.column>
                    <flux:table.column>Role</flux:table.column>
                    <flux:table.column>Akses</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->users as $user)
                        <flux:table.row :key="$user->id">
                            <flux:table.cell variant="strong">{{ $user->name }}</flux:table.cell>

                            <flux:table.cell>
                                @if ($user->role === 'admin')
                                    <flux:badge size="sm" color="green" inset="top bottom">Admin</flux:badge>
                                @else
                                    <flux:badge size="sm" inset="top bottom">Agent</flux:badge>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell>
                                {{ $user->role === 'admin' ? 'Semua chat & pengaturan' : 'Chat yang ditugaskan' }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            <div>
                <flux:button icon="plus" wire:click="create">Tambah agent</flux:button>
            </div>
        </flux:card>

        {{-- Audit log --}}
        <flux:card class="space-y-4">
            <flux:heading size="lg">Audit log</flux:heading>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Waktu</flux:table.column>
                    <flux:table.column>Pengguna</flux:table.column>
                    <flux:table.column>Aktivitas</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->logs as $log)
                        <flux:table.row :key="$log->id">
                            <flux:table.cell class="whitespace-nowrap">
                                @if ($log->created_at->isToday())
                                    {{ $log->created_at->format('H:i') }}
                                @elseif ($log->created_at->isYesterday())
                                    Kemarin
                                @else
                                    {{ $log->created_at->translatedFormat('d M Y') }}
                                @endif
                            </flux:table.cell>

                            <flux:table.cell>{{ $log->user?->name ?? 'Sistem' }}</flux:table.cell>

                            <flux:table.cell>{{ $log->action }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="3" class="py-8 text-center text-zinc-500">
                                Belum ada aktivitas.
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    </div>

    {{-- Modal tambah agent --}}
    <flux:modal name="agent-form" class="md:w-[26rem]">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">Tambah agent</flux:heading>

            <flux:input wire:model="name" label="Nama" placeholder="Nama lengkap" />
            <flux:input wire:model="email" type="email" label="Email" placeholder="agent@contoh.com" />
            <flux:input wire:model="password" type="password" label="Password" viewable />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
