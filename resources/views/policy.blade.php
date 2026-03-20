<x-guest-layout variant="card">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-12 col-xl-9">
                <div class="card border border-translucent shadow-sm">
                    <div class="card-body p-4 p-lg-5">
                        <div class="mb-4">
                            <x-authentication-card-logo />
                        </div>

                        <div class="text-body-secondary">
                            {!! $policy !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-guest-layout>
