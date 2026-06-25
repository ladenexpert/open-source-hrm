<div
    x-data="{
        canSubmitWithoutGps: @js($this->canSubmitAttendanceWithoutGps()),
        requiresSelfie: @js($this->requiresSelfieVerification()),
        async requestLocation(method) {
            if (! method) {
                return
            }

            const selfieContext = this.requiresSelfie
                ? await this.captureSelfie(method)
                : null

            if (this.requiresSelfie && ! selfieContext) {
                await this.selfieRequired(method)

                return
            }

            if (! navigator.geolocation) {
                await this.fail(method, 'unsupported', selfieContext)

                return
            }

            navigator.geolocation.getCurrentPosition(
                async (position) => {
                    const latitude = position?.coords?.latitude ?? null
                    const longitude = position?.coords?.longitude ?? null

                    if ((latitude === null) || (longitude === null)) {
                        await this.fail(method, 'unavailable', selfieContext)

                        return
                    }

                    await this.submit(method, latitude, longitude, selfieContext)
                },
                async (error) => {
                    await this.fail(method, this.resolveReason(error), selfieContext)
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0,
                },
            )
        },
        submit(method, latitude = null, longitude = null, selfieContext = null) {
            return this.$wire.submitAttendanceEvent(
                method,
                latitude,
                longitude,
                selfieContext?.dataUrl ?? null,
                selfieContext?.deviceInfo ?? {},
                selfieContext?.metadata ?? {},
            )
        },
        fail(method, reason, selfieContext = null) {
            return this.$wire.handleGeolocationFailure(
                method,
                reason,
                selfieContext?.dataUrl ?? null,
                selfieContext?.deviceInfo ?? {},
                selfieContext?.metadata ?? {},
            )
        },
        selfieRequired(method) {
            return this.$wire.handleSelfieRequirementNotMet(method)
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
        async captureSelfie(method) {
            const file = await this.chooseSelfieFile()

            if (! file) {
                return null
            }

            return {
                dataUrl: await this.toJpegDataUrl(file),
                deviceInfo: this.buildDeviceInfo(),
                metadata: {
                    original_filename: file.name ?? null,
                    original_size_bytes: file.size ?? null,
                    original_type: file.type ?? null,
                    attendance_method: method,
                    capture_source: 'portal-web',
                },
            }
        },
        chooseSelfieFile() {
            return new Promise((resolve) => {
                const input = this.$refs.selfieInput

                if (! input) {
                    resolve(null)

                    return
                }

                input.value = ''

                let settled = false

                const cleanup = () => {
                    input.removeEventListener('change', onChange)
                    window.removeEventListener('focus', onFocus, true)
                }

                const onChange = () => {
                    if (settled) {
                        return
                    }

                    settled = true
                    cleanup()

                    resolve((input.files && input.files[0]) ? input.files[0] : null)
                }

                const onFocus = () => {
                    window.setTimeout(() => {
                        if (settled) {
                            return
                        }

                        settled = true
                        cleanup()
                        resolve(null)
                    }, 500)
                }

                input.addEventListener('change', onChange, { once: true })
                window.addEventListener('focus', onFocus, true)
                input.click()
            })
        },
        async toJpegDataUrl(file) {
            const originalDataUrl = await this.readFile(file)

            if ((file.type ?? '').toLowerCase() === 'image/jpeg' || (file.type ?? '').toLowerCase() === 'image/jpg') {
                return originalDataUrl
            }

            const image = await this.loadImage(originalDataUrl)
            const canvas = document.createElement('canvas')

            canvas.width = image.naturalWidth || image.width
            canvas.height = image.naturalHeight || image.height

            const context = canvas.getContext('2d')

            if (! context) {
                return originalDataUrl
            }

            context.drawImage(image, 0, 0)

            return canvas.toDataURL('image/jpeg', 0.9)
        },
        readFile(file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader()

                reader.onload = () => resolve(reader.result)
                reader.onerror = () => reject(new Error('Failed to read selfie file.'))
                reader.readAsDataURL(file)
            })
        },
        loadImage(dataUrl) {
            return new Promise((resolve, reject) => {
                const image = new Image()

                image.onload = () => resolve(image)
                image.onerror = () => reject(new Error('Failed to load selfie image.'))
                image.src = dataUrl
            })
        },
        buildDeviceInfo() {
            return {
                user_agent: navigator.userAgent ?? null,
                platform: navigator.platform ?? null,
                language: navigator.language ?? null,
                screen_width: window.screen?.width ?? null,
                screen_height: window.screen?.height ?? null,
                timezone: Intl.DateTimeFormat().resolvedOptions().timeZone ?? null,
            }
        },
    }"
    x-on:attendance-request-geolocation.window="requestLocation($event.detail.method)"
    data-attendance-geo-capture-bridge="true"
    class="hidden"
>
    <input
        x-ref="selfieInput"
        type="file"
        accept="image/jpeg,image/jpg,image/png,image/webp,image/*"
        capture="user"
        class="hidden"
    />
</div>
