<?php
session_start();

// Check if the user is logged in and is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /public/auth/login.php");
    exit();
}

 $username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>

    <!-- CSS -->
    <link rel="stylesheet" href="../css/dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.css">
    <link rel="shortcut icon" href="https://cdn-icons-png.flaticon.com/512/295/295128.png">

    <!-- JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<style>
/* ============================================
   Notification Styles
   ============================================ */

/* Dropdown Container */
.notification-dropdown {
    display: none;
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
    width: 360px;
    max-height: 480px;
    overflow: hidden;
    z-index: 1000;
    border: 1px solid rgba(0, 0, 0, 0.08);
}

.notification-dropdown.show {
    display: flex;
    flex-direction: column;
}

/* Header */
.notification-header {
    padding: 16px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
}

.notification-header h5 {
    margin: 0;
    font-weight: 600;
    font-size: 1rem;
}

/* Scrollable List */
.notification-list {
    overflow-y: auto;
    flex: 1;
    max-height: 360px;
}

/* Individual Notification */
.notification-item {
    padding: 12px 16px;
    border-bottom: 1px solid #f5f5f5;
    transition: background-color 0.2s ease;
    cursor: default;
}

.notification-item:hover {
    background-color: #f8f9fa;
}

.notification-item:last-child {
    border-bottom: none;
}

/* Unread State */
.notification-item.unread {
    background-color: #e8f4fd;
    border-left: 3px solid #0d6efd;
}

.notification-item.unread:hover {
    background-color: #d6eaf8;
}

/* Avatar */
.notification-avatar {
    width: 40px;
    height: 40px;
    object-fit: cover;
    flex-shrink: 0;
}

/* Text */
.notification-text {
    font-size: 0.9rem;
    line-height: 1.4;
    color: #333;
}

.notification-item.unread .notification-text {
    font-weight: 500;
}

/* Footer */
.notification-footer {
    padding: 12px 16px;
    border-top: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    gap: 8px;
    flex-shrink: 0;
}

.notification-footer .btn {
    flex: 1;
    font-size: 0.8rem;
}

/* Badge */
.notification-badge {
    position: absolute;
    top: -4px;
    right: -4px;
    background: #dc3545;
    color: white;
    border-radius: 50%;
    min-width: 18px;
    height: 18px;
    display: none;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    font-weight: 700;
    padding: 0 4px;
    animation: badgePulse 2s infinite;
}

.notification-badge.show {
    display: flex;
}

@keyframes badgePulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

/* Bell Animation */
.bell-shake {
    animation: bellShake 0.5s ease;
}

@keyframes bellShake {
    0% { transform: rotate(0); }
    15% { transform: rotate(15deg); }
    30% { transform: rotate(-15deg); }
    45% { transform: rotate(10deg); }
    60% { transform: rotate(-10deg); }
    75% { transform: rotate(5deg); }
    100% { transform: rotate(0); }
}

/* Loading State */
.notification-loading {
    padding: 40px 16px;
    text-align: center;
    color: #999;
}

.notification-loading i {
    font-size: 1.5rem;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Search (kept for context) */
.search-container {
    position: relative;
}

.search-results {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #ddd;
    border-radius: 0 0 5px 5px;
    max-height: 300px;
    overflow-y: auto;
    z-index: 1000;
    display: none;
}

.search-results.show {
    display: block;
}

.search-results-item {
    padding: 10px;
    border-bottom: 1px solid #eee;
    cursor: pointer;
}

.search-results-item:hover {
    background-color: #f8f9fa;
}
</style>

<body>
<nav class="navbar navbar-expand-sm navbar-light bg-success">
    <div class="container">

        <a class="navbar-brand" href="#" style="font-weight:bold; color:white;"></a>

        <span style="color:white; font-weight:bold;">
            Welcome Admin, <?php echo htmlspecialchars($username); ?>
        </span>

        <button class="navbar-toggler d-lg-none" type="button" data-bs-toggle="collapse"
                data-bs-target="#collapsibleNavId">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="collapsibleNavId">
            <ul class="navbar-nav m-auto mt-2 mt-lg-0"></ul>

            <div class="d-flex align-items-center gap-2">
                <!-- Manage Users -->
                <a href="manage_users.php" class="btn btn-light" style="font-weight:bolder; color:orange;">
                    <i class="fa fa-tasks"></i>
                </a>
                
                <!-- Chat -->
                <a href="chat.php" class="btn btn-light" style="font-weight:bolder; color:purple;">
                    <i class="fa fa-comments"></i>
                </a>

                <!-- Search -->
                <div class="search-container">
                    <button class="btn btn-light" type="button" id="searchToggle">
                        <i class="fa fa-search"></i>
                    </button>
                    <div class="search-form" style="display: none; position: absolute; top: 40px; right: 0; background: white; padding: 12px; border-radius: 8px; box-shadow: 0 10px 40px rgba(0,0,0,0.15); width: 300px; z-index: 1000;">
                        <form method="GET" id="searchForm">
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" placeholder="Search users..." id="searchInput">
                                <button class="btn btn-success" type="submit">Go</button>
                            </div>
                        </form>
                        <div id="searchResults" class="search-results"></div>
                    </div>
                </div>

                <!-- Notifications -->
                <div class="position-relative">
                    <button class="btn btn-light position-relative" type="button" id="notificationsToggle">
                        <i class="fa fa-bell" id="bellIcon"></i>
                        <span id="notificationBadge" class="notification-badge">0</span>
                    </button>
                    
                    <div id="notificationsContainer" class="notification-dropdown">
                        <div class="notification-header">
                            <h5><i class="fa fa-bell"></i> Notifications</h5>
                            <button class="btn btn-sm btn-link text-decoration-none p-0 mark-all-read-header" title="Mark all as read">
                                <i class="fa fa-check-double"></i>
                            </button>
                        </div>
                        <div id="notificationsList" class="notification-list">
                            <!-- Notifications loaded here -->
                        </div>
                    </div>
                </div>

                <!-- Profile -->
                <a href="a_profile.php" class="btn btn-light" style="font-weight:bolder; color:orange;">
                    <i class="fa fa-user-circle"></i>
                </a>

                <!-- Logout Trigger -->
                <button class="btn btn-light" type="button" data-bs-toggle="modal" data-bs-target="#logoutModal" style="font-weight:bolder; color:red;">
                    <i class="fa fa-sign-out"></i>               
                </button>
            </div>
        </div>
    </div>
</nav>

<!-- Logout Confirmation Modal -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: rgba(15, 32, 39, 0.95); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 20px; color: white;">
            <div class="modal-header" style="border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                <h5 class="modal-title" id="logoutModalLabel">
                    <i class="fa fa-exclamation-triangle" style="color: #ffc107;"></i> Confirm Logout
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0" style="font-size: 1.1rem;">Are you sure you want to logout?</p>
            </div>
            <div class="modal-footer" style="border-top: 1px solid rgba(255, 255, 255, 0.1);">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 10px;">Cancel</button>
                <a href="/public/auth/logout.php" class="btn btn-danger" style="border-radius: 10px; font-weight: bold;">
                    <i class="fa fa-sign-out"></i> Yes, Logout
                </a>
            </div>
        </div>
    </div>
</div>

<script>
 $(document).ready(function() {
    
    // ============================================
    // Configuration
    // ============================================
    const NOTIFICATION_API = '/api/notifications.php';
    const POLL_INTERVAL = 30000; // 30 seconds
    
    let notificationsLoaded = false;
    let pollTimer = null;
    
    // ============================================
    // Dropdown Toggle Logic
    // ============================================
    $('#searchToggle').on('click', function(e) {
        e.stopPropagation();
        closeAllDropdowns();
        $('.search-form').toggle();
        if ($('.search-form').is(':visible')) {
            $('#searchInput').focus();
        }
    });
    
    $('#notificationsToggle').on('click', function(e) {
        e.stopPropagation();
        const $container = $('#notificationsContainer');
        const wasOpen = $container.hasClass('show');
        
        closeAllDropdowns();
        
        if (!wasOpen) {
            $container.addClass('show');
            
            // Load notifications only on first open or force refresh
            if (!notificationsLoaded) {
                loadNotifications();
            }
        }
    });
    
    // Close dropdowns on outside click
    $(document).on('click', function() {
        closeAllDropdowns();
    });
    
    // Prevent closing when clicking inside
    $('.search-form, #notificationsContainer').on('click', function(e) {
        e.stopPropagation();
    });
    
    function closeAllDropdowns() {
        $('.search-form').hide();
        $('#notificationsContainer').removeClass('show');
    }
    
    // ============================================
    // Notification Functions
    // ============================================
    
    function loadNotifications() {
        const $list = $('#notificationsList');
        
        // Show loading state
        $list.html('<div class="notification-loading"><i class="fa fa-spinner fa-spin"></i><p class="mt-2 mb-0">Loading...</p></div>');
        
        $.ajax({
            url: NOTIFICATION_API,
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $list.html(response.html);
                    notificationsLoaded = true;
                } else {
                    $list.html('<div class="text-danger text-center p-3">Failed to load notifications</div>');
                }
            },
            error: function() {
                $list.html('<div class="text-danger text-center p-3">Error loading notifications</div>');
            }
        });
    }
    
    function updateNotificationBadge() {
        $.ajax({
            url: NOTIFICATION_API,
            type: 'GET',
            data: { action: 'count' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const count = response.count;
                    const $badge = $('#notificationBadge');
                    
                    if (count > 0) {
                        $badge.text(count > 99 ? '99+' : count).addClass('show');
                        
                        // Shake bell if count increased
                        if (!$badge.data('last-count') || count > $badge.data('last-count')) {
                            $('#bellIcon').addClass('bell-shake');
                            setTimeout(() => $('#bellIcon').removeClass('bell-shake'), 500);
                        }
                        $badge.data('last-count', count);
                    } else {
                        $badge.removeClass('show').data('last-count', 0);
                    }
                }
            }
        });
    }
    
    function markAsRead(notifId) {
        $.ajax({
            url: NOTIFICATION_API,
            type: 'POST',
            data: { action: 'mark_read', notif_id: notifId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Remove unread styling
                    $(`.notification-item[data-notif-id="${notifId}"]`)
                        .removeClass('unread')
                        .find('.mark-read').fadeOut(200, function() { $(this).remove(); });
                    
                    updateNotificationBadge();
                }
            }
        });
    }
    
    function markAllAsRead() {
        $.ajax({
            url: NOTIFICATION_API,
            type: 'POST',
            data: { action: 'mark_all_read' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Remove all unread styling
                    $('.notification-item.unread').removeClass('unread');
                    $('.mark-read').fadeOut(200, function() { $(this).remove(); });
                    
                    updateNotificationBadge();
                }
            }
        });
    }
    
    // ============================================
    // Event Delegation for Notification Actions
    // ============================================
    
    // Mark single notification as read
    $('#notificationsList').on('click', '.mark-read', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const $item = $(this).closest('.notification-item');
        const notifId = $item.data('notif-id');
        
        if (notifId) {
            markAsRead(notifId);
        }
    });
    
    // Mark all as read (footer button)
    $('#notificationsList').on('click', '.mark-all-read', function(e) {
        e.preventDefault();
        markAllAsRead();
    });
    
    // Mark all as read (header button)
    $('.mark-all-read-header').on('click', function(e) {
        e.preventDefault();
        markAllAsRead();
    });
    
    // View profile action
    $('#notificationsList').on('click', '.view-profile', function(e) {
        e.preventDefault();
        const userId = $(this).data('user-id');
        if (userId) {
             window.location.href = `/api/profile_view.php?id=${userId}`;
        }
    });
    
    
    
    // ============================================
    // Search Functionality
    // ============================================
    
    $('#searchForm').on('submit', function(e) {
        e.preventDefault();
        const searchTerm = $('#searchInput').val().trim();
        
        if (searchTerm.length < 2) {
            $('#searchResults').html('<div class="p-2 text-muted small">Enter at least 2 characters</div>').addClass('show');
            return;
        }
        
        $.ajax({
            url: '/api/search.php',
            type: 'GET',
            data: { search: searchTerm },
            success: function(response) {
                $('#searchResults').html(response).addClass('show');
            },
            error: function() {
                $('#searchResults').html('<div class="p-2 text-danger">Error loading results</div>').addClass('show');
            }
        });
    });
    
    // Live search with debounce
    let searchTimeout;
    $('#searchInput').on('keyup', function() {
        clearTimeout(searchTimeout);
        const term = $(this).val().trim();
        
        if (term.length < 2) {
            $('#searchResults').removeClass('show');
            return;
        }
        
        searchTimeout = setTimeout(function() {
            $.ajax({
                url: '/api/search.php',
                type: 'GET',
                data: { search: term },
                success: function(response) {
                    $('#searchResults').html(response).addClass('show');
                }
            });
        }, 300);
    });
    
    // ============================================
    // Initial Load & Polling
    // ============================================
    
    // Get initial count
    updateNotificationBadge();
    
    // Start polling for new notifications
    function startPolling() {
        pollTimer = setInterval(updateNotificationBadge, POLL_INTERVAL);
    }
    
    // Pause polling when tab is not visible
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            clearInterval(pollTimer);
        } else {
            updateNotificationBadge();
            startPolling();
            // Refresh notification list if dropdown was open
            if ($('#notificationsContainer').hasClass('show')) {
                notificationsLoaded = false;
                loadNotifications();
            }
        }
    });
    
    startPolling();
    
});
</script>

</body>
</html>