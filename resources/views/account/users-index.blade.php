@props(['users'])

<x-layout>
    <x-banner heading="Users" back="account.index" />

    <form method="post" action="/account/users">
        <div class="table-layout">
            <table cellspacing="0" cellpadding="0" class="w-full table-auto">
                <thead>
                    <tr>
                        <th class="w-1">&nbsp;</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th class="hidden md:table-cell">Verified</th>
                        <th class="hidden md:table-cell">Under 14</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @unless (count($users) == 0)

                        @foreach ($users as $user)
                            <tr>
                                <td><input type="checkbox"></td>
                                <td>{{ $user->username }}</td>
                                <td>{{ $user->email }}</td>
                                <td class="hidden md:table-cell">{{ $user->formattedEmailVerifiedAt() }}</td>
                                <td class="hidden text-center md:table-cell">{{ $user->is_under_14 ? 'Yes' : 'No' }}</td>
                                <td class="action-column">
                                    <a href="#" title="Edit User"><i class="fa-solid fa-pen-to-square"></i></a>
                                    <a href="#" title="Delete User"><i
                                            class="fa-solid fa-trash hover:text-red"></i></a>
                                </td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="4">No users found</td>
                        </tr>
                    @endunless
                </tbody>
            </table>
        </div>
    </form>
    <div>
        <input type="submit" name="action" value="Edit">
        <input type="submit" name="action" value="Delete">
    </div>

    <div class="mt-6">
        {{ $users->links() }}
    </div>

</x-layout>
