@foreach($nodes as $department)
    <div class="{{ $level > 0 ? 'ml-6' : '' }}">
        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer py-1">
            <input type="checkbox" name="allowed_departments[]" value="{{ $department->id }}"
                   class="rounded border-gray-300 text-[#5B4FE8] focus:ring-[#5B4FE8]"
                   {{ in_array($department->id, $selected) ? 'checked' : '' }}>
            {{ $department->name }}
        </label>

        @if($children->has($department->id))
            @include('admin.partials.department-checkboxes', [
                'nodes'    => $children->get($department->id),
                'children' => $children,
                'selected' => $selected,
                'level'    => $level + 1,
            ])
        @endif
    </div>
@endforeach
