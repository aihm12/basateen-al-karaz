@foreach($fields as $name=>$field)
@php
$type=$field['type']??'text';
$value=$record?->{$name}??($field['default']??'');
if (is_bool($value)) { $value=(int)$value; }
if ($type==='datetime-local' && $value) { $value=\Carbon\Carbon::parse($value)->format('Y-m-d\TH:i'); }
if ($type==='time' && $value) { $value=substr($value,0,5); }
if (old('_form') === $formKey && $type !== 'password') { $value=old($name,$value); }
@endphp
<label>{{ $field['label'] }}
@if(isset($field['options']))<select name="{{ $name }}" @required($field['required']??false)>@foreach($field['options'] as $key=>$label)<option value="{{ $key }}" @selected((string)$value===(string)$key)>{{ $label }}</option>@endforeach</select>
@elseif($type==='textarea')<textarea name="{{ $name }}" rows="3" @required($field['required']??false)>{{ $value }}</textarea>
@else<input name="{{ $name }}" type="{{ $type }}" value="{{ $type==='password' ? '' : $value }}" @required($field['required']??false) @if(isset($field['step'])) step="{{ $field['step'] }}" @endif @if(isset($field['min'])) min="{{ $field['min'] }}" @endif @if(isset($field['max'])) max="{{ $field['max'] }}" @endif @if($type==='password') autocomplete="new-password" @endif>
@endif
@if(isset($field['hint']))<small class="muted">{{ $field['hint'] }}</small>@endif
</label>
@endforeach


