@props(['name', 'label' => null, 'options' => [], 'value' => null, 'required' => false, 'placeholder' => null, 'help' => null, 'col' => null])
@php
    $id = $attributes->get('id', 'f_'.str_replace(['[', ']', '.'], '_', $name));
    $dotName = str_replace(['[', ']'], ['.', ''], $name);
    $current = (string) old($dotName, $value instanceof \BackedEnum ? $value->value : $value);
@endphp
<div class="{{ $col ?? 'mb-3' }}">
    @if ($label)
        <label for="{{ $id }}" class="form-label {{ $required ? 'required' : '' }}">{{ $label }}</label>
    @endif
    <select name="{{ $name }}" id="{{ $id }}" @required($required)
            {{ $attributes->except('id')->merge(['class' => 'form-select'.($errors->has($dotName) ? ' is-invalid' : '')]) }}>
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $key => $text)
            <option value="{{ $key }}" @selected($current === (string) $key)>{{ $text }}</option>
        @endforeach
    </select>
    @error($dotName)<div class="invalid-feedback">{{ $message }}</div>@enderror
    @if ($help)
        <div class="form-text">{{ $help }}</div>
    @endif
</div>
