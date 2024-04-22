<x-layout>
    <x-mast>Users</x-mast>

    <x-container>
        <div class="flex my-4 items-center">
            <div class="flex-1">
                <x-ui.button type="link" href="{{ route('admin.user.create') }}">Create User</x-ui.button>
            </div>
            <div class="flex-1">
                <x-ui.search name="search" label="Search" />
            </div>
        </div>

        @if($users->isEmpty())
            <x-none-found item="posts" search="{{ request()->get('search') }}" />
        @else
            <x-ui.table>
                <x-slot:header>
                    <th>First name</th>
                    <th>Surname</th>
                    <th>Email</th>
                    <th>Actions</th>
                </x-slot:header>
                <x-slot:body>
                    @foreach ($users as $user)
                        <tr>
                            <td>{{ $user->firstname }}</td>
                            <td>{{ $user->surname }}</td>
                            <td>{{ $user->email }}</td>
                            <td class="flex justify-center gap-3">
                                <a href="{{ route('admin.user.edit', $user) }}" class="hover:text-primary-color"><i class="fa-solid fa-pen-to-square"></i></a>
                                @if($user->id !== '1')
                                    <form method="POST" action="{{ route('admin.user.destroy', $user) }}" x-data x-on:submit.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete account?', 'Are you sure you want to delete this account? This action cannot be undone', $el)">
                                        @method('DELETE')
                                        @csrf
                                        <button type="submit" class="hover:text-red-600"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                  @endforeach
                </x-slot:body>
            </x-ui.table>

            {{ $users->links() }}
        @endif

    </x-container>
</x-layout>
