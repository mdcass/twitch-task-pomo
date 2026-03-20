<div class="card border border-translucent auth-card">
    <div class="card-body pe-md-0">
        <div class="row align-items-center gx-0 gy-7">
            @isset($aside)
                <div class="col-auto bg-body-highlight dark__bg-gray-1100 rounded-3 position-relative overflow-hidden auth-title-box">
                    <div class="bg-holder" style="background-image:url({{ asset('images/auth/phoenix-auth-panel-38.png') }});">
                    </div>

                    {{ $aside ?? '' }}
                </div>
            @endisset

            <div class="col mx-auto">
                <div class="auth-form-box">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </div>
</div>
