@assets
    <link rel="stylesheet" href="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css" />
    <script src="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js"></script>
@endassets

@php
    $statePath = $field->getStatePath();
    $initialValue = $field->getState();
@endphp

<div
    class="space-y-3"
    x-data="goongBoundaryPicker($wire.entangle('{{ $statePath }}').live, @js($mapKey), @js($initialValue))"
    x-init="init()"
>
    <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm dark:border-white/10 dark:bg-white/5">
        <div>
            <p class="font-medium text-gray-950 dark:text-white" x-text="instruction"></p>
            <p class="mt-1 text-gray-500 dark:text-gray-400">Click góc tây nam trước, sau đó click góc đông bắc trên bản đồ.</p>
        </div>
        <button type="button" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-white dark:border-white/20 dark:text-gray-200 dark:hover:bg-white/10" x-on:click="reset()">
            Chọn lại
        </button>
    </div>

    <div x-ref="map" wire:ignore style="height: 28rem" class="overflow-hidden rounded-xl border border-gray-200 bg-gray-100 dark:border-white/10"></div>

    <div class="rounded-lg border border-gray-200 bg-white p-3 text-xs text-gray-600 dark:border-white/10 dark:bg-gray-900 dark:text-gray-300">
        <div class="flex items-center justify-between gap-3">
            <span class="font-medium">Bounds đã chọn</span>
            <span x-text="coordinatesLabel"></span>
        </div>
        <code class="mt-2 block break-all whitespace-pre-wrap text-[11px]" x-text="state || 'Chưa chọn đủ 2 điểm.'"></code>
    </div>
</div>

@once
    <script>
        window.goongBoundaryPicker = (state, mapKey, initialValue) => ({
            state,
            mapKey,
            initialValue,
            map: null,
            markers: [],
            points: [],
            instruction: 'Chọn điểm thứ nhất',
            coordinatesLabel: '0/2 điểm',
            init() {
                this.points = this.parseInitial(this.initialValue);
                this.$nextTick(() => this.createMap());
            },
            parseInitial(value) {
                try {
                    const parsed = typeof value === 'string' ? JSON.parse(value) : value;
                    if (parsed?.type !== 'Bounds') return [];
                    return [[Number(parsed.southwest.lng), Number(parsed.southwest.lat)], [Number(parsed.northeast.lng), Number(parsed.northeast.lat)]];
                } catch (_) { return []; }
            },
            createMap() {
                if (!window.mapboxgl || !this.mapKey) {
                    this.instruction = 'Chưa cấu hình GOONG_MAP_KEY cho trang quản trị';
                    return;
                }
                mapboxgl.accessToken = this.mapKey;
                this.map = new mapboxgl.Map({
                    container: this.$refs.map,
                    style: `https://tiles.goong.io/assets/goong_map_web.json?api_key=${encodeURIComponent(this.mapKey)}`,
                    center: [106.7009, 10.7769],
                    zoom: 11,
                    attributionControl: true,
                });
                this.map.addControl(new mapboxgl.NavigationControl(), 'top-right');
                this.map.on('load', () => {
                    this.map.on('click', (event) => this.addPoint([event.lngLat.lng, event.lngLat.lat]));
                    this.draw();
                });
            },
            addPoint(point) {
                if (this.points.length >= 2) this.points = [];
                this.points.push(point);
                this.draw();
                this.commit();
            },
            reset() {
                this.points = [];
                this.commit();
                this.draw();
            },
            commit() {
                if (this.points.length < 2) {
                    this.state = '';
                    return;
                }
                const west = Math.min(this.points[0][0], this.points[1][0]);
                const east = Math.max(this.points[0][0], this.points[1][0]);
                const south = Math.min(this.points[0][1], this.points[1][1]);
                const north = Math.max(this.points[0][1], this.points[1][1]);
                this.state = JSON.stringify({
                    type: 'Bounds',
                    southwest: { lat: Number(south.toFixed(6)), lng: Number(west.toFixed(6)) },
                    northeast: { lat: Number(north.toFixed(6)), lng: Number(east.toFixed(6)) },
                });
            },
            draw() {
                this.coordinatesLabel = `${this.points.length}/2 điểm`;
                this.instruction = this.points.length === 0 ? 'Chọn điểm thứ nhất' : this.points.length === 1 ? 'Chọn điểm thứ hai để hoàn tất khu vực' : 'Đã tạo khu vực, có thể chọn lại';
                if (!this.map || !this.map.isStyleLoaded()) return;
                for (const marker of this.markers) marker.remove();
                this.markers = this.points.map((point) => new mapboxgl.Marker({ color: '#146B52' }).setLngLat(point).addTo(this.map));
                const feature = this.points.length === 2 ? this.boundsFeature() : { type: 'FeatureCollection', features: [] };
                const source = this.map.getSource('service-area-boundary');
                if (source) {
                    source.setData(feature);
                    return;
                }
                this.map.addSource('service-area-boundary', { type: 'geojson', data: feature });
                this.map.addLayer({ id: 'service-area-boundary-fill', type: 'fill', source: 'service-area-boundary', paint: { 'fill-color': '#146B52', 'fill-opacity': 0.18 } });
                this.map.addLayer({ id: 'service-area-boundary-line', type: 'line', source: 'service-area-boundary', paint: { 'line-color': '#146B52', 'line-width': 3 } });
            },
            boundsFeature() {
                const west = Math.min(this.points[0][0], this.points[1][0]);
                const east = Math.max(this.points[0][0], this.points[1][0]);
                const south = Math.min(this.points[0][1], this.points[1][1]);
                const north = Math.max(this.points[0][1], this.points[1][1]);
                return { type: 'Feature', geometry: { type: 'Polygon', coordinates: [[[west, south], [east, south], [east, north], [west, north], [west, south]]] }, properties: {} };
            },
        });
    </script>
@endonce
