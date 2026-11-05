@props(['value', 'signed' => false])

@php($formatted = \App\Support\Timesheet::formatHours(abs($value)))
<span {{ $attributes->class(['hours', 'positive' => $signed && $value > 0, 'negative' => $signed && $value < 0]) }}>{{ $signed ? ($value < 0 ? '−' : '+') : '' }}{{ $formatted }}&nbsp;h</span>
