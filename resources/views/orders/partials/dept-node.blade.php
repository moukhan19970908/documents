@php $depth = $depth ?? 0; @endphp
<label class="flex items-center gap-3 px-3 py-2 hover:bg-gray-50 rounded-lg cursor-pointer" style="padding-left: {{ 0.75 + $depth * 1.75 }}rem">
    <input type="checkbox" @change="toggleDept({{ $node['id'] }})" :checked="isDeptChecked({{ $node['id'] }})"
           class="w-5 h-5 rounded accent-[#5B4FE8] cursor-pointer">
    <span class="text-sm {{ $depth === 0 ? 'font-semibold text-gray-900' : 'text-gray-700' }}">{{ $node['name'] }}</span>
    <span class="ml-auto text-xs text-gray-400">({{ $node['count'] }} чел.)</span>
</label>
@foreach($node['children'] as $child)
    @include('orders.partials.dept-node', ['node' => $child, 'depth' => $depth + 1])
@endforeach
