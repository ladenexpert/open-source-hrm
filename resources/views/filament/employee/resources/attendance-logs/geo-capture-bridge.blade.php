<div
    x-data="{
        canSubmitWithoutGps: @js($this->canSubmitAttendanceWithoutGps()),
        async requestLocation(method) {
            if (! method) {
                return
            }

            if (! navigator.geolocation) {
                await this.fail(method, 'unsupported')

                return
            }

            navigator.geolocation.getCurrentPosition(
                async (position) => {
                    const latitude = position?.coords?.latitude ?? null
                    const longitude = position?.coords?.longitude ?? null

                    if ((latitude === null) || (longitude === null)) {
                        await this.fail(method, 'unavailable')

                        return
                    }

                    await this.submit(method, latitude, longitude)
                },
                async (error) => {
                    await this.fail(method, this.resolveReason(error))
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0,
                },
            )
        },
        submit(method, latitude = null, longitude = null) {
            return this.$wire.submitAttendanceEvent(method, latitude, longitude)
        },
        fail(method, reason) {
            return this.$wire.handleGeolocationFailure(method, reason)
        },
        resolveReason(error) {
            if (! error || typeof error.code === 'undefined') {
                return 'unavailable'
            }

            switch (error.code) {
                case error.PERMISSION_DENIED:
                    return 'permission_denied'
                case error.TIMEOUT:
                    return 'timeout'
                case error.POSITION_UNAVAILABLE:
                    return 'unavailable'
                default:
                    return 'unavailable'
            }
        },
    }"
    x-on:attendance-request-geolocation.window="requestLocation($event.detail.method)"
    data-attendance-geo-capture-bridge="true"
    class="hidden"
></div>
