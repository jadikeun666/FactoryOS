<button {{ $attributes->merge(['type' => 'submit', 'class' => 'auth-btn-primary']) }}>
    {{ $slot }}
</button>
