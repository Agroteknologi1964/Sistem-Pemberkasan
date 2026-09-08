<?php

namespace App\Livewire\User;

use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Rule;
use Livewire\Component;

class ChangePassword extends Component
{
    #[Rule('required|string|min:8')]
    public string $current_password = '';

    #[Rule('required|string|min:8|confirmed')]
    public string $password = '';

    #[Rule('required|string|min:8')]
    public string $password_confirmation = '';

    public bool $success = false;

    public function save()
    {
        $this->validate();

        $user = auth()->user();

        if (! Hash::check($this->current_password, $user->password)) {
            $this->addError('current_password', 'Password saat ini tidak cocok.');
            return;
        }

        $user->update([
            'password'              => Hash::make($this->password),
            'force_change_password' => false,
        ]);

        $this->reset(['current_password', 'password', 'password_confirmation']);
        $this->success = true;

        session()->flash('success', 'Password berhasil diubah.');
        return redirect()->route('user.dashboard');
    }

    public function render()
    {
        return view('livewire.user.change-password');
    }
}
