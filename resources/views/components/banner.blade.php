@props(['style' => session('flash.bannerStyle', 'success'), 'message' => session('flash.banner')])

<div x-data="{{ json_encode(['show' => true, 'style' => $style, 'message' => $message]) }}"
     style="display: none;"
     x-show="show && message"
     x-on:banner-message.window="
        style = event.detail.style;
        message = event.detail.message;
        show = true;
     ">
    <div class="container position-fixed top-0 start-50 translate-middle-x mt-3" style="z-index: 1080;">
        <div class="alert shadow-lg border-0 d-flex align-items-center justify-content-between gap-3"
             :class="{
                'alert-success': style == 'success',
                'alert-danger': style == 'danger',
                'alert-warning': style == 'warning',
                'alert-primary': style != 'success' && style != 'danger' && style != 'warning'
             }">
            <div class="d-flex align-items-center gap-3">
                <svg x-show="style == 'success'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <svg x-show="style == 'danger'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                    </svg>
                    <svg x-show="style == 'warning'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.007v.008H12v-.008z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.94-1.623 3.374-1.623 4.314 0l6.69 11.54c.94 1.623-.22 3.65-2.157 3.65H5.81c-1.938 0-3.098-2.027-2.157-3.65l6.69-11.54z" />
                    </svg>
                    <svg x-show="style != 'success' && style != 'danger' && style != 'warning'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                    </svg>

                <span class="fw-semibold" x-text="message"></span>
            </div>

            <button type="button" class="btn-close" aria-label="Dismiss" x-on:click="show = false"></button>
        </div>
    </div>
</div>
