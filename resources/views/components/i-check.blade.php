<div>
    <input type="hidden" name="{{ $name }}" value="0">
    <div class="icheck-primary" title="{{ $title }}">
        <input type="checkbox" name="{{ $name }}" value="1" {{ ($value == 1 || $value == null) ? 'checked' : '' }} id="{{ $name }}">
        <label for="{{ $name }}">{{ $title }}</label>
    </div>
</div>
