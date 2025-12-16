// Map initialization and markers handling
let map;
let markers = [];
let infoWindow;

function initMap() {
    // Initialize the map centered on a default location (you can set this to your city's coordinates)
    map = new google.maps.Map(document.getElementById('assignments-map'), {
        zoom: 12,
        center: { lat: 0.3476, lng: 32.5825 }, // Default to Kampala coordinates
        styles: [
            {
                featureType: "poi",
                elementType: "labels",
                stylers: [{ visibility: "off" }]
            }
        ]
    });

    infoWindow = new google.maps.InfoWindow();

    // Load assignment locations
    loadAssignmentLocations();
}

function loadAssignmentLocations() {
    const assignments = document.querySelectorAll('.data-table tbody tr');
    
    assignments.forEach(assignment => {
        // Handle both old and new table structures
        const addressCell = assignment.querySelector('[data-label="Address"], [data-label="Location"]');
        if (!addressCell) return;
        
        const address = addressCell.textContent.trim().replace('Google Maps', '').replace('Waze', '').replace('Call', '').replace('Copy', '').trim();
        const id = assignment.querySelector('[data-label="ID"]').textContent.replace('#', '').trim();
        
        const wasteTypeCell = assignment.querySelector('[data-label="Waste Type"]');
        const wasteType = wasteTypeCell ? wasteTypeCell.textContent.trim() : 'Unknown';
        
        const statusCell = assignment.querySelector('[data-label="Status"], [data-label="Assignment Status"]');
        const status = statusCell ? statusCell.textContent.trim() : 'Unknown';
        
        // Get customer info if available
        const customerCell = assignment.querySelector('[data-label="Customer"]');
        const customer = customerCell ? customerCell.textContent.trim().split('\n')[0] : '';
        
        const quantityCell = assignment.querySelector('[data-label="Quantity"]');
        const quantity = quantityCell ? quantityCell.textContent.trim() : '';
        
        // Geocode the address to get coordinates
        geocodeAddress(address, (location) => {
            if (location) {
                addMarker(location, {
                    id: id,
                    address: address,
                    wasteType: wasteType,
                    status: status,
                    customer: customer,
                    quantity: quantity
                });
            }
        });
    });
}

function geocodeAddress(address, callback) {
    const geocoder = new google.maps.Geocoder();
    geocoder.geocode({ address: address }, (results, status) => {
        if (status === 'OK') {
            callback(results[0].geometry.location);
        } else {
            console.warn('Geocode failed for address:', address, status);
            callback(null);
        }
    });
}

function addMarker(location, data) {
    // Create marker
    const marker = new google.maps.Marker({
        position: location,
        map: map,
        title: `Request #${data.id}`,
        icon: getMarkerIcon(data.status)
    });

    // Store marker reference
    markers.push(marker);

    // Create info window content
    const encodedAddress = encodeURIComponent(data.address);
    const googleMapsUrl = `https://www.google.com/maps/search/?api=1&query=${encodedAddress}`;
    const wazeUrl = `https://www.waze.com/ul?q=${encodedAddress}`;
    
    const content = `
        <div class="map-info-window" style="min-width: 250px; font-family: Arial, sans-serif;">
            <h3 style="margin: 0 0 10px 0; color: #11998e; border-bottom: 2px solid #11998e; padding-bottom: 5px;">
                <i class="fas fa-recycle"></i> Request #${data.id}
            </h3>
            ${data.customer ? `<p style="margin: 5px 0;"><strong><i class="fas fa-user"></i> Customer:</strong> ${data.customer}</p>` : ''}
            <p style="margin: 5px 0;"><strong><i class="fas fa-map-marker-alt"></i> Address:</strong><br>${data.address}</p>
            <p style="margin: 5px 0;"><strong><i class="fas fa-recycle"></i> Waste Type:</strong> ${data.wasteType}</p>
            ${data.quantity ? `<p style="margin: 5px 0;"><strong><i class="fas fa-weight"></i> Quantity:</strong> ${data.quantity}</p>` : ''}
            <p style="margin: 5px 0;"><strong><i class="fas fa-info-circle"></i> Status:</strong> <span class="status-badge status-${data.status.toLowerCase().replace(' ', '-')}">${data.status}</span></p>
            <div style="margin-top: 10px; display: flex; gap: 5px; flex-wrap: wrap;">
                <a href="${googleMapsUrl}" target="_blank" style="display: inline-flex; align-items: center; gap: 5px; padding: 8px 12px; background: #4285f4; color: white; text-decoration: none; border-radius: 5px; font-size: 0.9em;">
                    <i class="fas fa-map"></i> Navigate (Google)
                </a>
                <a href="${wazeUrl}" target="_blank" style="display: inline-flex; align-items: center; gap: 5px; padding: 8px 12px; background: #33ccff; color: white; text-decoration: none; border-radius: 5px; font-size: 0.9em;">
                    <i class="fas fa-route"></i> Waze
                </a>
            </div>
        </div>
    `;

    // Add click listener to show info window
    marker.addListener('click', () => {
        infoWindow.setContent(content);
        infoWindow.open(map, marker);
    });

    // Center map on first marker
    if (markers.length === 1) {
        map.setCenter(location);
    }
}

function getMarkerIcon(status) {
    // Define marker colors based on status
    const colors = {
        'Assigned': '#17a2b8',
        'In Progress': '#ffc107',
        'Completed': '#28a745'
    };

    const color = colors[status] || '#17a2b8';

    return {
        path: google.maps.SymbolPath.CIRCLE,
        fillColor: color,
        fillOpacity: 0.9,
        strokeColor: '#ffffff',
        strokeWeight: 2,
        scale: 8
    };
}

function clearMarkers() {
    markers.forEach(marker => marker.setMap(null));
    markers = [];
}

// Update markers when filters are applied
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('assignment-search');
    const filterStatus = document.getElementById('filter-status');
    const filterWasteType = document.getElementById('filter-waste-type');

    const updateMarkers = () => {
        clearMarkers();
        loadAssignmentLocations();
    };

    searchInput.addEventListener('input', updateMarkers);
    filterStatus.addEventListener('change', updateMarkers);
    filterWasteType.addEventListener('change', updateMarkers);
});