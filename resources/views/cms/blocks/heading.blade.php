@php($lvl = max(1, min(6, (int) $level)))
<h{{ $lvl }}>{{ $text }}</h{{ $lvl }}>
