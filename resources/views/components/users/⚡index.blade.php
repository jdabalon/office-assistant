<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $role_id = '';

    public ?int $editingUserId = null;
    public string $editName = '';
    public string $editEmail = '';
    public string $editRoleId = '';

    public ?int $resetUserId = null;
    public string $newPassword = '';
    public string $newPasswordConfirmation = '';

    public ?int $deletingUserId = null;

    public function with(): array
    {
        return [
            'users' => User::with('role')
                ->orderBy('name')
                ->get(),

            'roles' => Role::orderBy('name')
                ->get(),
        ];
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role_id' => ['required', 'exists:roles,id'],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role_id' => $validated['role_id'],
        ]);

        $this->reset([
            'name',
            'email',
            'password',
            'role_id',
        ]);

        $this->modal('create-user')->close();
    }

    public function editUser(int $userId): void
    {
        $user = User::findOrFail($userId);

        $this->editingUserId = $user->id;
        $this->editName = $user->name;
        $this->editEmail = $user->email;
        $this->editRoleId = (string) $user->role_id;

        $this->modal('edit-user')->show();
    }

    public function updateUser(): void
    {
        $user = User::findOrFail($this->editingUserId);

        $validated = $this->validate([
            'editName' => ['required', 'string', 'max:255'],
            'editEmail' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'editRoleId' => ['required', 'exists:roles,id'],
        ]);

        $user->update([
            'name' => $validated['editName'],
            'email' => $validated['editEmail'],
            'role_id' => $validated['editRoleId'],
        ]);

        $this->reset([
            'editingUserId',
            'editName',
            'editEmail',
            'editRoleId',
        ]);

        $this->modal('edit-user')->close();
    }

    public function openResetPassword(int $userId): void
    {
        $this->resetUserId = $userId;
        $this->newPassword = '';
        $this->newPasswordConfirmation = '';

        $this->modal('reset-password')->show();
    }

    public function resetPassword(): void
    {
        $this->validate([
            'newPassword' => [
                'required',
                'string',
                'min:8',
                'same:newPasswordConfirmation',
            ],
            'newPasswordConfirmation' => [
                'required',
                'string',
                'min:8',
            ],
        ]);

        $user = User::findOrFail($this->resetUserId);

        $user->update([
            'password' => $this->newPassword,
        ]);

        $this->reset([
            'resetUserId',
            'newPassword',
            'newPasswordConfirmation',
        ]);

        $this->modal('reset-password')->close();
    }

    public function confirmDelete(int $userId): void
    {
        $this->deletingUserId = $userId;

        $this->modal('delete-user')->show();
    }

    public function deleteUser(): void
    {
        $user = User::findOrFail($this->deletingUserId);

        if ($user->id === auth()->id()) {
            $this->addError(
                'deleteUser',
                'You cannot delete your own account.'
            );

            return;
        }

        $user->delete();

        $this->reset('deletingUserId');

        $this->modal('delete-user')->close();
    }
};
?>

<div class="p-6">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold">Users</h1>
            <p class="text-sm text-zinc-500">
                Manage employee accounts.
            </p>
        </div>

        <flux:modal.trigger name="create-user">
            <flux:button variant="primary">
                Add User
            </flux:button>
        </flux:modal.trigger>
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
        <table class="w-full">
            <thead class="bg-zinc-50 dark:bg-zinc-800">
                <tr>
                    <th class="px-6 py-3 text-left text-sm font-medium">
                        Name
                    </th>

                    <th class="px-6 py-3 text-left text-sm font-medium">
                        Email
                    </th>

                    <th class="px-6 py-3 text-left text-sm font-medium">
                        Role
                    </th>

                    <th class="px-6 py-3 text-right text-sm font-medium">
                        Actions
                    </th>
                </tr>
            </thead>

            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($users as $user)
                    <tr>
                        <td class="px-6 py-4">
                            {{ $user->name }}
                        </td>

                        <td class="px-6 py-4">
                            {{ $user->email }}
                        </td>

                        <td class="px-6 py-4">
                            {{ $user->role?->name ?? 'No role' }}
                        </td>

                        <td class="px-6 py-4">
                            <div class="flex justify-end gap-2">
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    wire:click="editUser({{ $user->id }})"
                                >
                                    Edit
                                </flux:button>

                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    wire:click="openResetPassword({{ $user->id }})"
                                >
                                    Reset Password
                                </flux:button>

                                @if ($user->id !== auth()->id())
                                    <flux:button
                                        size="sm"
                                        variant="danger"
                                        wire:click="confirmDelete({{ $user->id }})"
                                    >
                                        Delete
                                    </flux:button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-6 py-8 text-center text-zinc-500">
                            No users found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <flux:modal name="create-user" class="md:w-[500px]">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">
                    Add User
                </flux:heading>

                <flux:text class="mt-2">
                    Create an account for an employee.
                </flux:text>
            </div>

            <flux:input
                wire:model="name"
                label="Name"
                placeholder="Employee name"
                required
            />

            <flux:input
                wire:model="email"
                label="Email"
                type="email"
                placeholder="employee@office.local"
                required
            />

            <flux:input
                wire:model="password"
                label="Temporary Password"
                type="password"
                required
            />

            <flux:select
                wire:model="role_id"
                label="Role"
                required
            >
                <option value="">Select role</option>

                @foreach ($roles as $role)
                    <option value="{{ $role->id }}">
                        {{ $role->name }}
                    </option>
                @endforeach
            </flux:select>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">
                        Cancel
                    </flux:button>
                </flux:modal.close>

                <flux:button
                    type="submit"
                    variant="primary"
                >
                    Create User
                </flux:button>
            </div>
        </form>
    </flux:modal>
    <flux:modal name="edit-user" class="md:w-[500px]">
    <form wire:submit="updateUser" class="space-y-6">
        <div>
            <flux:heading size="lg">
                Edit User
            </flux:heading>

            <flux:text class="mt-2">
                Update the employee account information.
            </flux:text>
        </div>

        <flux:input
            wire:model="editName"
            label="Name"
            required
        />

        <flux:input
            wire:model="editEmail"
            label="Email"
            type="email"
            required
        />

        <flux:select
            wire:model="editRoleId"
            label="Role"
            required
        >
            <option value="">Select role</option>

            @foreach ($roles as $role)
                <option value="{{ $role->id }}">
                    {{ $role->name }}
                </option>
            @endforeach
        </flux:select>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">
                    Cancel
                </flux:button>
            </flux:modal.close>

            <flux:button type="submit" variant="primary">
                Save Changes
            </flux:button>
        </div>
    </form>
</flux:modal>

<flux:modal name="reset-password" class="md:w-[500px]">
    <form wire:submit="resetPassword" class="space-y-6">
        <div>
            <flux:heading size="lg">
                Reset Password
            </flux:heading>

            <flux:text class="mt-2">
                Set a new temporary password for this user.
            </flux:text>
        </div>

        <flux:input
            wire:model="newPassword"
            label="New Password"
            type="password"
            required
        />

        <flux:input
            wire:model="newPasswordConfirmation"
            label="Confirm New Password"
            type="password"
            required
        />

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">
                    Cancel
                </flux:button>
            </flux:modal.close>

            <flux:button type="submit" variant="primary">
                Reset Password
            </flux:button>
        </div>
    </form>
</flux:modal>

<flux:modal name="delete-user" class="md:w-[450px]">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">
                Delete User
            </flux:heading>

            <flux:text class="mt-2">
                Are you sure you want to delete this user account?
                This action cannot be undone.
            </flux:text>
        </div>

        @error('deleteUser')
            <div class="text-sm text-red-600">
                {{ $message }}
            </div>
        @enderror

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">
                    Cancel
                </flux:button>
            </flux:modal.close>

            <flux:button
                variant="danger"
                wire:click="deleteUser"
            >
                Delete User
            </flux:button>
        </div>
    </div>
</flux:modal>
</div>