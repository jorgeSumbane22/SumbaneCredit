@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'required' => false, 'help' => null, 'col' => null, 'prefix' => null, 'suffix' => null])
@php
    $id = $attributes->get('id', 'f_'.str_replace(['[', ']', '.'], '_', $name));
    $dotName = str_replace(['[', ']'], ['.', ''], $name);
    $current = $type === 'password' ? null : old($dotName, $value);
@endphp
<div class="{{ $col ?? 'mb-3' }}">
    @if ($label)
        <label for="{{ $id }}" class="form-label {{ $required ? 'required' : '' }}">{{ $label }}</label>
    @endif
    @if ($prefix || $suffix)<div class="input-group">@endif
        @if ($prefix)<span class="input-group-text">{{ $prefix }}</span>@endif
        <input type="{{ $type }}" name="{{ $name }}" id="{{ $id }}"
               @if (! is_null($current)) value="{{ $current }}" @endif
               @required($required)
               {{ $attributes->except('id')->merge(['class' => 'form-control'.($errors->has($dotName) ? ' is-invalid' : '')]) }}>
        @if ($suffix)<span class="input-group-text">{{ $suffix }}</span>@endif
        @error($dotName)<div class="invalid-feedback">{{ $message }}</div>@enderror
    @if ($prefix || $suffix)</div>@endif
    @if ($help)
        <div class="form-text">{{ $help }}</div>
    @endif
</div>
