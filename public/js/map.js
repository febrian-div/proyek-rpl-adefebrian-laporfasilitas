/**
 * LaporFasilitas - Leaflet Map & Geolocation Helper
 */

window.LaporMap = {
  // Default coordinates (Jakarta center)
  defaultLat: -6.2088,
  defaultLng: 106.8456,
  defaultZoom: 14,

  /**
   * Initialize interactive coordinate picker
   */
  initPicker(elementId, latInputId, lngInputId, addressInputId = null, initialLat = null, initialLng = null) {
    const mapEl = document.getElementById(elementId);
    if (!mapEl || typeof L === 'undefined') return null;

    const lat = initialLat ? parseFloat(initialLat) : this.defaultLat;
    const lng = initialLng ? parseFloat(initialLng) : this.defaultLng;

    const map = L.map(elementId).setView([lat, lng], this.defaultZoom);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    const marker = L.marker([lat, lng], { draggable: true }).addTo(map);
    marker.bindPopup("<b>Titik Lokasi Kerusakan</b><br>Geser pin ke titik persis.").openPopup();

    const latInput = document.getElementById(latInputId);
    const lngInput = document.getElementById(lngInputId);
    const addressInput = addressInputId ? document.getElementById(addressInputId) : null;

    const updateInputs = (latitude, longitude) => {
      if (latInput) latInput.value = latitude.toFixed(7);
      if (lngInput) lngInput.value = longitude.toFixed(7);
    };

    updateInputs(lat, lng);

    marker.on('dragend', function () {
      const position = marker.getLatLng();
      updateInputs(position.lat, position.lng);
    });

    map.on('click', function (e) {
      marker.setLatLng(e.latlng);
      updateInputs(e.latlng.lat, e.latlng.lng);
      marker.openPopup();
    });

    // Handle Geolocation Button
    const geoBtn = document.getElementById('btn-get-location');
    const geoStatus = document.getElementById('geo-status');

    const fetchCurrentLocation = () => {
      if (!navigator.geolocation) {
        if (geoStatus) geoStatus.innerHTML = '<span class="text-danger"><i class="fa-solid fa-triangle-exclamation"></i> Browser tidak mendukung geolokasi.</span>';
        return;
      }

      if (geoStatus) geoStatus.innerHTML = '<span class="text-primary"><i class="fa-solid fa-spinner fa-spin"></i> Mendeteksi lokasi GPS Anda...</span>';

      navigator.geolocation.getCurrentPosition(
        (pos) => {
          const userLat = pos.coords.latitude;
          const userLng = pos.coords.longitude;
          map.setView([userLat, userLng], 16);
          marker.setLatLng([userLat, userLng]);
          updateInputs(userLat, userLng);
          marker.bindPopup("<b>Lokasi GPS Anda Terdeteksi</b>").openPopup();
          if (geoStatus) geoStatus.innerHTML = '<span style="color: #059669;"><i class="fa-solid fa-circle-check"></i> Lokasi GPS berhasil ditemukan!</span>';
        },
        (err) => {
          let msg = 'Izin lokasi ditolak.';
          if (err.code === 2) msg = 'Posisi tidak dapat ditemukan.';
          if (err.code === 3) msg = 'Waktu permintaan lokasi habis.';
          if (geoStatus) geoStatus.innerHTML = `<span style="color: #d97706;"><i class="fa-solid fa-circle-info"></i> ${msg} Silakan pilih titik secara manual pada peta.</span>`;
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
      );
    };

    if (geoBtn) {
      geoBtn.addEventListener('click', (e) => {
        e.preventDefault();
        fetchCurrentLocation();
      });
    }

    // Auto-request location if no initial coords
    if (!initialLat && navigator.geolocation) {
      fetchCurrentLocation();
    }

    return { map, marker };
  },

  /**
   * Initialize static viewer for report location
   */
  initViewer(elementId, lat, lng, title = 'Lokasi Fasilitas') {
    const mapEl = document.getElementById(elementId);
    if (!mapEl || typeof L === 'undefined') return null;

    const parsedLat = parseFloat(lat);
    const parsedLng = parseFloat(lng);

    const map = L.map(elementId).setView([parsedLat, parsedLng], 15);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    const marker = L.marker([parsedLat, parsedLng]).addTo(map);
    marker.bindPopup(`<b>${title}</b><br><span style="font-size:0.75rem; color:#64748b;">Lat: ${parsedLat}, Lng: ${parsedLng}</span>`).openPopup();

    return { map, marker };
  }
};
