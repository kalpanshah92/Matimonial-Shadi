/**
 * Matrimonial Shadi - Main JavaScript
 */

$(document).ready(function () {

    // Back to Top Button
    $(window).scroll(function () {
        if ($(this).scrollTop() > 300) {
            $('#backToTop').addClass('show');
        } else {
            $('#backToTop').removeClass('show');
        }
    });

    $('#backToTop').click(function (e) {
        e.preventDefault();
        $('html, body').animate({ scrollTop: 0 }, 600);
    });

    // Toggle Password Visibility
    $('.toggle-password').click(function () {
        var target = $(this).data('target');
        var input = $('#' + target);
        var icon = $(this).find('i');

        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            icon.removeClass('bi-eye').addClass('bi-eye-slash');
        } else {
            input.attr('type', 'password');
            icon.removeClass('bi-eye-slash').addClass('bi-eye');
        }
    });

    // Phone number validation (Indian)
    $('#phone').on('input', function () {
        this.value = this.value.replace(/[^0-9]/g, '').substring(0, 10);
    });

    // Auto-dismiss alerts after 5 seconds
    setTimeout(function () {
        $('.alert-dismissible').fadeOut(500, function () {
            $(this).remove();
        });
    }, 5000);

    // Profile photo preview
    $('input[name="profile_photo"]').on('change', function () {
        var file = this.files[0];
        if (file) {
            if (file.size > 5 * 1024 * 1024) {
                alert('File size must be less than 5MB');
                this.value = '';
                return;
            }
            var reader = new FileReader();
            reader.onload = function (e) {
                $('#photoPreview').attr('src', e.target.result).show();
            };
            reader.readAsDataURL(file);
        }
    });

    // Shortlist toggle
    $(document).on('click', '.btn-shortlist', function (e) {
        e.preventDefault();
        var btn = $(this);
        var profileId = btn.data('profile-id');

        $.ajax({
            url: 'api/shortlist.php',
            method: 'POST',
            data: { profile_id: profileId },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    btn.toggleClass('active');
                    var icon = btn.find('i');
                    if (btn.hasClass('active')) {
                        icon.removeClass('bi-heart').addClass('bi-heart-fill');
                        btn.attr('title', 'Remove from Shortlist');
                    } else {
                        icon.removeClass('bi-heart-fill').addClass('bi-heart');
                        btn.attr('title', 'Add to Shortlist');
                    }
                }
            }
        });
    });

    // Send connection request
    $(document).on('click', '.btn-connect', function (e) {
        e.preventDefault();
        var btn = $(this);
        var profileId = btn.data('profile-id');

        if (confirm('Send connection request to this profile?')) {
            $.ajax({
                url: 'api/connection.php',
                method: 'POST',
                data: { action: 'send', profile_id: profileId },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        btn.text('Request Sent').prop('disabled', true).removeClass('btn-primary').addClass('btn-secondary');
                    } else {
                        alert(response.message || 'Failed to send request.');
                    }
                }
            });
        }
    });

    // Accept/Decline connection request
    $(document).on('click', '.btn-accept-request, .btn-decline-request', function (e) {
        e.preventDefault();
        var btn = $(this);
        var requestId = btn.data('request-id');
        var action = btn.hasClass('btn-accept-request') ? 'accept' : 'decline';

        $.ajax({
            url: 'api/connection.php',
            method: 'POST',
            data: { action: action, request_id: requestId },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    btn.closest('.request-item').fadeOut(300, function () {
                        $(this).remove();
                    });
                }
            }
        });
    });

    // Chat functionality
    var chatRefreshInterval;

    function loadMessages(contactId) {
        if (!contactId) return;

        $.ajax({
            url: 'api/chat.php',
            method: 'GET',
            data: { action: 'get_messages', contact_id: contactId },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    var html = '';
                    response.messages.forEach(function (msg) {
                        var cls = msg.is_mine ? 'message-sent' : 'message-received';
                        html += '<div class="d-flex ' + (msg.is_mine ? 'justify-content-end' : '') + '">';
                        html += '<div class="message-bubble ' + cls + '">';
                        html += '<p class="mb-0">' + msg.message + '</p>';
                        html += '<small class="message-time">' + msg.time + '</small>';
                        html += '</div></div>';
                    });
                    $('#chatMessages').html(html);
                    scrollChatToBottom();
                }
            }
        });
    }

    function scrollChatToBottom() {
        var chatBox = document.getElementById('chatMessages');
        if (chatBox) {
            chatBox.scrollTop = chatBox.scrollHeight;
        }
    }

    // Send chat message
    $('#chatForm').on('submit', function (e) {
        e.preventDefault();
        var input = $('#chatInput');
        var message = input.val().trim();
        var contactId = $('#currentContactId').val();

        if (!message || !contactId) return;

        $.ajax({
            url: 'api/chat.php',
            method: 'POST',
            data: { action: 'send', contact_id: contactId, message: message },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    input.val('');
                    loadMessages(contactId);
                }
            }
        });
    });

    // Select chat contact
    $(document).on('click', '.chat-contact', function () {
        var contactId = $(this).data('contact-id');
        var contactName = $(this).data('contact-name');

        $('.chat-contact').removeClass('active');
        $(this).addClass('active');

        $('#currentContactId').val(contactId);
        $('#chatContactName').text(contactName);
        $('#chatInputArea').show();

        loadMessages(contactId);

        // Start auto-refresh
        clearInterval(chatRefreshInterval);
        chatRefreshInterval = setInterval(function () {
            loadMessages(contactId);
        }, 5000);
    });

    // Notification mark as read
    $(document).on('click', '.notification-item', function () {
        var notifId = $(this).data('notif-id');
        $.post('api/notifications.php', { action: 'mark_read', notification_id: notifId });
    });

    // Search form - dynamic range sliders
    if ($('#ageRange').length) {
        $('#minAge, #maxAge').on('change', function () {
            var min = parseInt($('#minAge').val()) || 18;
            var max = parseInt($('#maxAge').val()) || 60;
            if (min > max) {
                $(this).val($(this).attr('id') === 'minAge' ? max : min);
            }
        });
    }

    // Admin - Approve/Reject profiles
    $(document).on('click', '.btn-approve-profile, .btn-reject-profile', function () {
        var btn = $(this);
        var userId = btn.data('user-id');
        var action = btn.hasClass('btn-approve-profile') ? 'approve' : 'reject';

        if (confirm('Are you sure you want to ' + action + ' this profile?')) {
            $.ajax({
                url: 'api/profiles.php',
                method: 'POST',
                data: { action: action, user_id: userId },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        btn.closest('tr').find('.status-badge').text(action + 'd')
                            .removeClass('bg-warning').addClass(action === 'approve' ? 'bg-success' : 'bg-danger');
                    }
                }
            });
        }
    });

    // Form validation visual feedback
    $('form').on('submit', function () {
        $(this).addClass('was-validated');
    });

    // Animate elements on scroll
    function animateOnScroll() {
        $('.step-card, .community-card, .profile-card, .feature-card, .testimonial-card').each(function () {
            var elementTop = $(this).offset().top;
            var viewportBottom = $(window).scrollTop() + $(window).height();

            if (elementTop < viewportBottom - 50) {
                $(this).addClass('animate__animated animate__fadeInUp');
            }
        });
    }

    $(window).on('scroll', animateOnScroll);
    animateOnScroll();
});
