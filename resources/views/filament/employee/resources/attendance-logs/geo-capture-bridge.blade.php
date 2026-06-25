<div
    x-data="{
        canSubmitWithoutGps: @js($this->canSubmitAttendanceWithoutGps()),
        requiresSelfie: @js($this->requiresSelfieVerification()),
        deviceUuidStorageKey: 'hrms_attendance_device_uuid',
        async requestLocation(method) {
            if (! method) {
                return
            }

            const deviceContext = this.buildDeviceContext()
            const selfieContext = this.requiresSelfie
                ? await this.captureSelfie(method)
                : null

            if (this.requiresSelfie && ! selfieContext) {
                await this.selfieRequired(method)

                return
            }

            if (! navigator.geolocation) {
                await this.fail(method, 'unsupported', selfieContext, deviceContext)

                return
            }

            navigator.geolocation.getCurrentPosition(
                async (position) => {
                    const latitude = position?.coords?.latitude ?? null
                    const longitude = position?.coords?.longitude ?? null

                    if ((latitude === null) || (longitude === null)) {
                        await this.fail(method, 'unavailable', selfieContext, deviceContext)

                        return
                    }

                    await this.submit(method, latitude, longitude, selfieContext, deviceContext)
                },
                async (error) => {
                    await this.fail(method, this.resolveReason(error), selfieContext, deviceContext)
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0,
                },
            )
        },
        submit(method, latitude = null, longitude = null, selfieContext = null, deviceContext = null) {
            return this.$wire.submitAttendanceEvent(
                method,
                latitude,
                longitude,
                selfieContext?.dataUrl ?? null,
                deviceContext?.deviceInfo ?? {},
                selfieContext?.metadata ?? {},
                deviceContext?.deviceUuid ?? null,
            )
        },
        fail(method, reason, selfieContext = null, deviceContext = null) {
            return this.$wire.handleGeolocationFailure(
                method,
                reason,
                selfieContext?.dataUrl ?? null,
                deviceContext?.deviceInfo ?? {},
                selfieContext?.metadata ?? {},
                deviceContext?.deviceUuid ?? null,
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
        buildDeviceContext() {
            const deviceUuid = this.resolveStoredDeviceUuid()

            return {
                deviceUuid,
                deviceInfo: this.buildDeviceInfo(deviceUuid),
            }
        },
        resolveStoredDeviceUuid() {
            const generatedUuid = this.generateDeviceUuid()

            try {
                const existingUuid = window.localStorage?.getItem(this.deviceUuidStorageKey)

                if (existingUuid) {
                    return existingUuid
                }

                window.localStorage?.setItem(this.deviceUuidStorageKey, generatedUuid)
            } catch (error) {
                return generatedUuid
            }

            return generatedUuid
        },
        generateDeviceUuid() {
            if (window.crypto?.randomUUID) {
                return window.crypto.randomUUID()
            }

            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (character) => {
                const random = Math.floor(Math.random() * 16)
                const value = character === 'x'
                    ? random
                    : ((random & 0x3) | 0x8)

                return value.toString(16)
            })
        },
        resolveBrowserName() {
            const userAgent = (navigator.userAgent ?? '').toLowerCase()

            if (userAgent.includes('edg/')) {
                return 'Edge'
            }

            if (userAgent.includes('opr/') || userAgent.includes('opera')) {
                return 'Opera'
            }

            if (userAgent.includes('chrome/')) {
                return 'Chrome'
            }

            if (userAgent.includes('firefox/')) {
                return 'Firefox'
            }

            if (userAgent.includes('safari/') && ! userAgent.includes('chrome/')) {
                return 'Safari'
            }

            return null
        },
        buildDeviceInfo(deviceUuid = null) {
            const browser = this.resolveBrowserName()
            const platform = navigator.userAgentData?.platform ?? navigator.platform ?? null
            const deviceName = [browser, platform].filter(Boolean).join(' on ')

            return {
                browser,
                device_name: deviceName || null,
                device_uuid: deviceUuid,
                user_agent: navigator.userAgent ?? null,
                platform,
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
