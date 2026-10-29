<div class="flex items-center justify-center p-4">
    <p>{{ __('transbank::emulator.redirecting') }}</p>
    <form id="transbank-emulator-return" action="{{ $url }}" method="POST">
        @foreach($params as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach
    </form>
    <script>document.getElementById('transbank-emulator-return').submit();</script>
</div>
