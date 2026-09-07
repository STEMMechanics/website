<x-layout>
    <x-mast backRoute="admin.supplier.index" backTitle="Suppliers" :title="$supplier->exists ? 'Edit supplier' : 'Create supplier'" />
    <x-container class="py-5 sm:py-8">
        <form method="POST" action="{{ $supplier->exists ? route('admin.supplier.update', $supplier) : route('admin.supplier.store') }}" class="mx-auto max-w-3xl" data-record-form>
            @csrf
            @if($supplier->exists) @method('PUT') @endif
            <x-ui.input name="name" label="Supplier name" :value="$supplier->name" :error="$errors->first('name') ?: $errors->first('supplier')" required />
            <x-ui.select name="category_id" label="Default cost centre" :value="$supplier->category_id" required>
                <option value="">Choose a cost centre</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) old('category_id', $supplier->category_id) === (string) $category->id)>{{ $category->name }}{{ !$category->active ? ' (archived)' : '' }}</option>
                @endforeach
            </x-ui.select>
            @if($supplier->exists && !$supplier->category_id && count($supplier->splits) > 1)
                <p class="mt-3 text-sm text-amber-800">This supplier has an existing split default. Choose a cost centre to replace it with a 100% default. Expense overrides will be retained.</p>
            @endif
            <x-finance.save>Save supplier</x-finance.save>
        </form>
    </x-container>
</x-layout>
