@props(['action', 'formKey', 'method' => 'POST', 'block' => null, 'submitLabel' => 'Save'])

@php
    $isActive = old('form_key') === $formKey;
    $value = fn (string $field, ?string $default) => $isActive ? old($field) : $default;
@endphp

<form method="POST" action="{{ $action }}" class="block-form">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif
    <input type="hidden" name="form_key" value="{{ $formKey }}">
    <label class="field">
        <span>Start</span>
        <input type="time" name="start_time" required value="{{ $value('start_time', $block ? $block::inputTime($block->start_time) : null) }}">
    </label>
    <label class="field">
        <span>End</span>
        <input type="time" name="end_time" required value="{{ $value('end_time', $block ? $block::inputTime($block->end_time) : null) }}">
    </label>
    <label class="field grow">
        <span>Label</span>
        <input type="text" name="label" maxlength="255" placeholder="Optional" value="{{ $value('label', $block?->label) }}">
    </label>
    <button type="submit" class="button">{{ $submitLabel }}</button>
</form>

@if ($isActive && $errors->any())
    <ul class="errors">
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
@endif
