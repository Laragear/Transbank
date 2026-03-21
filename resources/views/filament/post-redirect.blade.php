<div class="flex items-center justify-center p-4">
    <p>{{ __('transbank::filament.redirecting') }}</p>
    <form id="transbank-form" action="{{ $url }}" method="POST"></form>
    <script>document.getElementById('transbank-form').submit();</script>
</div>
