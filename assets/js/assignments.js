// Assignments Table Functionality
document.addEventListener('DOMContentLoaded', function() {
    // Elements
    const searchInput = document.getElementById('assignment-search');
    const filterStatus = document.getElementById('filter-status');
    const filterWasteType = document.getElementById('filter-waste-type');
    const assignmentsTable = document.querySelector('.data-table');
    const tableHeaders = assignmentsTable.querySelectorAll('th[data-sortable]');
    
    let assignments = [];
    let currentSort = {
        column: null,
        direction: 'asc'
    };

    // Initialize assignments data
    document.querySelectorAll('.data-table tbody tr').forEach(row => {
        assignments.push({
            element: row,
            id: row.querySelector('[data-label="ID"]').textContent.trim(),
            wasteType: row.querySelector('[data-label="Waste Type"]').textContent.trim(),
            quantity: parseFloat(row.querySelector('[data-label="Quantity"]').textContent),
            address: row.querySelector('[data-label="Address"]').textContent.trim(),
            requestStatus: row.querySelector('[data-label="Request Status"]').textContent.trim(),
            assignmentStatus: row.querySelector('[data-label="Assignment Status"]').textContent.trim(),
        });
    });

    // Search functionality
    searchInput.addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        filterAssignments();
    });

    // Status filter
    filterStatus.addEventListener('change', function() {
        filterAssignments();
    });

    // Waste type filter
    filterWasteType.addEventListener('change', function() {
        filterAssignments();
    });

    // Sorting functionality
    tableHeaders.forEach(header => {
        header.addEventListener('click', function() {
            const column = this.dataset.sort;
            if (currentSort.column === column) {
                currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
            } else {
                currentSort.column = column;
                currentSort.direction = 'asc';
            }

            // Update header classes
            tableHeaders.forEach(h => h.classList.remove('sort-asc', 'sort-desc'));
            this.classList.add(`sort-${currentSort.direction}`);

            sortAssignments(column, currentSort.direction);
            filterAssignments();
        });
    });

    function filterAssignments() {
        const searchTerm = searchInput.value.toLowerCase();
        const statusFilter = filterStatus.value;
        const wasteTypeFilter = filterWasteType.value;

        assignments.forEach(assignment => {
            let show = true;

            // Apply search filter
            if (searchTerm) {
                const searchString = `${assignment.id} ${assignment.wasteType} ${assignment.address}`.toLowerCase();
                show = searchString.includes(searchTerm);
            }

            // Apply status filter
            if (show && statusFilter) {
                show = assignment.assignmentStatus === statusFilter;
            }

            // Apply waste type filter
            if (show && wasteTypeFilter) {
                show = assignment.wasteType === wasteTypeFilter;
            }

            // Show/hide row
            assignment.element.style.display = show ? '' : 'none';
        });

        // Update visible assignments count
        updateVisibleCount();
    }

    function sortAssignments(column, direction) {
        const multiplier = direction === 'asc' ? 1 : -1;
        
        assignments.sort((a, b) => {
            let valueA = a[column];
            let valueB = b[column];

            if (column === 'quantity') {
                return (valueA - valueB) * multiplier;
            }
            
            return valueA.localeCompare(valueB) * multiplier;
        });

        // Reorder table rows
        const tbody = assignmentsTable.querySelector('tbody');
        assignments.forEach(assignment => {
            tbody.appendChild(assignment.element);
        });
    }

    function updateVisibleCount() {
        const visibleCount = assignments.filter(a => a.element.style.display !== 'none').length;
        const badge = document.querySelector('.assignments-card .badge');
        if (badge) {
            badge.textContent = `${visibleCount} Active`;
        }
    }
});