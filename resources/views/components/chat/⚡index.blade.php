<?php

use App\Models\Conversation;
use App\Models\User;
use Livewire\Component;

new class extends Component
{
    public ?int $conversationId = null;
    public string $message = '';

    public function with(): array
    {
        $user = auth()->user();

        return [
            'conversations' => $user->conversations()
                ->with([
                    'users',
                    'messages' => fn ($query) => $query->latest()->limit(1),
                ])
                ->latest('conversations.updated_at')
                ->get(),

            'selectedConversation' => $this->conversationId
                ? Conversation::with([
                    'users',
                    'messages.user',
                ])
                    ->whereHas('users', fn ($query) =>
                        $query->where('users.id', $user->id)
                    )
                    ->find($this->conversationId)
                : null,

            'coworkers' => User::where('id', '!=', $user->id)
                ->orderBy('name')
                ->get(),
        ];
    }

    public function selectConversation(int $conversationId): void
    {
        $conversation = auth()->user()
            ->conversations()
            ->findOrFail($conversationId);

        $this->conversationId = $conversation->id;
    }

    public function startConversation(int $userId): void
    {
        $currentUser = auth()->user();

        abort_if($currentUser->id === $userId, 403);

        $otherUser = User::findOrFail($userId);

        /*
         * Find an existing DIRECT conversation containing exactly
         * these two users.
         */
        $conversation = Conversation::query()
            ->where('type', 'direct')
            ->whereHas('users', fn ($query) =>
                $query->where('users.id', $currentUser->id)
            )
            ->whereHas('users', fn ($query) =>
                $query->where('users.id', $otherUser->id)
            )
            ->withCount('users')
            ->get()
            ->firstWhere('users_count', 2);

        if (! $conversation) {
            $conversation = Conversation::create([
                'type' => 'direct',
                'created_by' => $currentUser->id,
            ]);

            $conversation->users()->attach([
                $currentUser->id,
                $otherUser->id,
            ]);
        }

        $this->conversationId = $conversation->id;
    }

    public function sendMessage(): void
    {
        $this->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $conversation = auth()->user()
            ->conversations()
            ->findOrFail($this->conversationId);

        $conversation->messages()->create([
            'user_id' => auth()->id(),
            'content' => trim($this->message),
        ]);

        $conversation->touch();

        $this->reset('message');
        $this->dispatch('message-sent');
    }
};
?>

<div class="p-6">
    <div class="mb-6">
        <h1 class="text-2xl font-semibold">Chat</h1>

        <p class="text-sm text-zinc-500">
            Message your coworkers.
        </p>
    </div>

    <div class="grid h-[70vh] overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700 lg:grid-cols-[300px_1fr]">

        {{-- Sidebar --}}
        <aside class="overflow-y-auto border-r border-zinc-200 dark:border-zinc-700">

            <div class="border-b border-zinc-200 p-4 dark:border-zinc-700">
                <flux:heading size="sm">
                    Conversations
                </flux:heading>
            </div>

            @forelse ($conversations as $conversation)

                @php
                    $otherUser = $conversation->users
                        ->firstWhere('id', '!=', auth()->id());

                    $lastMessage = $conversation->messages->first();
                @endphp

                <button
                    type="button"
                    wire:click="selectConversation({{ $conversation->id }})"
                    class="w-full border-b border-zinc-100 px-4 py-3 text-left hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800"
                >
                    <div class="font-medium">
                        {{ $otherUser?->name ?? 'Conversation' }}
                    </div>

                    @if ($lastMessage)
                        <div class="mt-1 truncate text-xs text-zinc-500">
                            {{ $lastMessage->content }}
                        </div>
                    @endif
                </button>

            @empty

                <div class="p-4 text-sm text-zinc-500">
                    No conversations yet.
                </div>

            @endforelse


            {{-- Coworkers --}}

            <div class="border-y border-zinc-200 p-4 dark:border-zinc-700">
                <flux:heading size="sm">
                    Coworkers
                </flux:heading>
            </div>

            @foreach ($coworkers as $coworker)

                <button
                    type="button"
                    wire:click="startConversation({{ $coworker->id }})"
                    class="w-full px-4 py-3 text-left hover:bg-zinc-50 dark:hover:bg-zinc-800"
                >
                    <div class="font-medium">
                        {{ $coworker->name }}
                    </div>

                    <div class="text-xs text-zinc-500">
                        {{ $coworker->role?->name ?? 'Employee' }}
                    </div>
                </button>

            @endforeach

        </aside>


        {{-- Chat Area --}}

        <section class="flex min-h-0 flex-col">

            @if ($selectedConversation)

                @php
                    $chatUser = $selectedConversation->users
                        ->firstWhere('id', '!=', auth()->id());
                @endphp

                {{-- Header --}}

                <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
                    <div class="font-semibold">
                        {{ $chatUser?->name ?? 'Conversation' }}
                    </div>

                    <div class="text-xs text-zinc-500">
                        {{ $chatUser?->role?->name ?? 'Employee' }}
                    </div>
                </div>


                {{-- Messages --}}

               <div
                    id="messages-container"
                    wire:poll.2s 
                    class="flex-1 space-y-4 overflow-y-auto p-6">

                    @forelse ($selectedConversation->messages as $chatMessage)

                        <div class="{{ $chatMessage->user_id === auth()->id()
                            ? 'flex justify-end'
                            : 'flex justify-start' }}">

                            <div class="max-w-[70%]">

                                <div
                                    class="{{ $chatMessage->user_id === auth()->id()
                                        ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900'
                                        : 'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-white' }}
                                        rounded-2xl px-4 py-2"
                                >
                                    {{ $chatMessage->content }}
                                </div>

                                <div
                                    class="{{ $chatMessage->user_id === auth()->id()
                                        ? 'text-right'
                                        : 'text-left' }}
                                        mt-1 text-xs text-zinc-400"
                                >
                                    {{ $chatMessage->created_at->format('g:i A') }}
                                </div>

                            </div>

                        </div>

                    @empty

                        <div class="flex h-full items-center justify-center text-sm text-zinc-500">
                            No messages yet. Start the conversation.
                        </div>

                    @endforelse

                </div>


                {{-- Composer --}}

                <form
                    wire:submit="sendMessage"
                    class="border-t border-zinc-200 p-4 dark:border-zinc-700"
                >
                    <div class="flex gap-3">

                        <flux:input
                            wire:model="message"
                            placeholder="Type a message..."
                            class="flex-1"
                            autocomplete="off"
                        />

                        <flux:button
                            type="submit"
                            variant="primary"
                        >
                            Send
                        </flux:button>

                    </div>

                    @error('message')
                        <p class="mt-2 text-sm text-red-600">
                            {{ $message }}
                        </p>
                    @enderror
                </form>

            @else

                <div class="flex flex-1 items-center justify-center">

                    <div class="text-center">
                        <flux:heading size="lg">
                            Your messages
                        </flux:heading>

                        <flux:text class="mt-2">
                            Select a coworker to start a conversation.
                        </flux:text>
                    </div>

                </div>

            @endif

        </section>

    </div>
</div>
@script
<script>
    const scrollToBottom = () => {
        const container = document.getElementById('messages-container');

        if (container) {
            container.scrollTop = container.scrollHeight;
        }
    };

    scrollToBottom();

    $wire.on('message-sent', () => {
        setTimeout(scrollToBottom, 50);
    });
</script>
@endscript