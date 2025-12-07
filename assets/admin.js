jQuery(document).ready(function($) {
    'use strict';

    // Elements
    const $addFeedBtn = $('#add-feed-btn');
    const $feedUrl = $('#feed-url');
    const $feedName = $('#feed-name');
    const $loading = $('#ppps-loading');
    const $overview = $('#ppps-overview');
    const $podcastDetail = $('#ppps-podcast-detail');
    const $backToOverview = $('#back-to-overview');
    const $removeFeedFromStats = $('#remove-feed-from-stats');

    // State
    let currentPodcastId = null;

    // Load overview on page load
    loadOverview();

    // Add feed button
    $addFeedBtn.on('click', function() {
        addFeed();
    });

    // Back to overview
    $backToOverview.on('click', function() {
        showOverview();
    });

    // Remove feed from stats
    $removeFeedFromStats.on('click', function() {
        if (currentPodcastId) {
            removeFeedFromStats(currentPodcastId);
        }
    });

    // Podcast card click (delegated)
    $(document).on('click', '.ppps-podcast-card', function() {
        const podcastId = $(this).data('podcast-id');
        showPodcastDetail(podcastId);
    });

    /**
     * Add feed
     */
    function addFeed() {
        const feedUrl = $feedUrl.val().trim();
        const feedName = $feedName.val().trim();

        if (!feedUrl || !feedName) {
            alert(pppsData.strings.fillAllFields);
            return;
        }

        $.ajax({
            url: pppsData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ppps_add_feed',
                nonce: pppsData.nonce,
                feed_url: feedUrl,
                podcast_name: feedName
            },
            beforeSend: function() {
                $addFeedBtn.prop('disabled', true).text(pppsData.strings.adding || 'Adding...');
            },
            success: function(response) {
                if (response.success) {
                    $feedUrl.val('');
                    $feedName.val('');
                    alert(response.data.message);
                    loadOverview();
                } else {
                    alert(response.data || pppsData.strings.errorAdding);
                }
            },
            error: function() {
                alert(pppsData.strings.errorAdding);
            },
            complete: function() {
                $addFeedBtn.prop('disabled', false).html('<span class="dashicons dashicons-plus-alt"></span> ' + (pppsData.strings.addFeed || 'Add Feed'));
            }
        });
    }

    /**
     * Remove feed from stats (deletes feed, episodes, and stats)
     */
    function removeFeedFromStats(feedId) {
        if (!confirm(pppsData.strings.confirmRemove)) {
            return;
        }

        $.ajax({
            url: pppsData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ppps_delete_feed',
                nonce: pppsData.nonce,
                feed_id: feedId
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    showOverview();
                } else {
                    alert(response.data || pppsData.strings.errorDeleting);
                }
            },
            error: function() {
                alert(pppsData.strings.errorDeleting);
            }
        });
    }

    /**
     * Load overview
     */
    function loadOverview() {
        $loading.show();
        $overview.hide();
        $podcastDetail.hide();

        $.ajax({
            url: pppsData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ppps_get_overview',
                nonce: pppsData.nonce
            },
            success: function(response) {
                if (response.success) {
                    renderOverview(response.data.podcasts);
                } else {
                    alert(pppsData.strings.errorLoading);
                }
            },
            error: function() {
                alert(pppsData.strings.errorLoading);
            },
            complete: function() {
                $loading.hide();
            }
        });
    }

    /**
     * Render overview
     */
    function renderOverview(podcasts) {
        if (!podcasts || podcasts.length === 0) {
            $overview.hide();
            return;
        }

        let html = '';

        podcasts.forEach(podcast => {
            const thumbnail = podcast.thumbnail_url || 'https://via.placeholder.com/300x300?text=No+Image';
            const accesses = parseInt(podcast.total_accesses) || 0;

            html += `
                <div class="ppps-podcast-card" data-podcast-id="${podcast.id}">
                    <img src="${escapeHtml(thumbnail)}" alt="${escapeHtml(podcast.podcast_name)}" class="ppps-podcast-card-thumb" onerror="this.src='https://via.placeholder.com/300x300?text=No+Image'">
                    <h3 class="ppps-podcast-card-title">${escapeHtml(podcast.podcast_name)}</h3>
                    <div class="ppps-podcast-card-stats">
                        <span class="dashicons dashicons-download"></span>
                        <span class="ppps-podcast-card-accesses">${formatNumber(accesses)}</span>
                        <span>${pppsData.strings.accesses || 'accesses'}</span>
                    </div>
                </div>
            `;
        });

        $('#podcasts-grid').html(html);
        $overview.show();
    }

    /**
     * Show overview
     */
    function showOverview() {
        currentPodcastId = null;
        $podcastDetail.hide();
        loadOverview();
    }

    /**
     * Show podcast detail
     */
    function showPodcastDetail(podcastId) {
        currentPodcastId = podcastId;

        $overview.hide();
        $loading.show();

        $.ajax({
            url: pppsData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ppps_get_podcast_stats',
                nonce: pppsData.nonce,
                podcast_id: podcastId
            },
            success: function(response) {
                if (response.success) {
                    renderPodcastDetail(response.data);
                } else {
                    alert(pppsData.strings.errorLoading);
                    showOverview();
                }
            },
            error: function() {
                alert(pppsData.strings.errorLoading);
                showOverview();
            },
            complete: function() {
                $loading.hide();
            }
        });
    }

    /**
     * Render podcast detail
     */
    function renderPodcastDetail(data) {
        const podcast = data.podcast;
        const thumbnail = podcast.thumbnail_url || 'https://via.placeholder.com/300x300?text=No+Image';

        // Set header
        $('#podcast-thumbnail').attr('src', thumbnail).on('error', function() {
            $(this).attr('src', 'https://via.placeholder.com/300x300?text=No+Image');
        });
        $('#podcast-title').text(podcast.podcast_name);
        $('#podcast-url').text(podcast.feed_url);
        $('#podcast-total-accesses').text(formatNumber(data.total_accesses));

        // Render by platform
        renderBarChart('#chart-by-platform', data.by_platform, 'platform');

        // Render by episode
        renderBarChart('#chart-by-episode', data.by_episode, 'episode_title');

        $podcastDetail.show();
    }

    /**
     * Render bar chart
     */
    function renderBarChart(selector, data, labelKey) {
        const $container = $(selector);

        if (!data || data.length === 0) {
            const message = labelKey === 'episode_title'
                ? 'No episode data available yet'
                : (pppsData.strings.noData || 'No data available');
            $container.html(`<p class="ppps-no-data">${message}</p>`);
            return;
        }

        const maxValue = Math.max(...data.map(item => parseInt(item.count)));

        let html = '<div class="ppps-bar-chart">';

        data.forEach(item => {
            const label = item[labelKey] || 'Unknown';
            const count = parseInt(item.count);
            const percentage = maxValue > 0 ? (count / maxValue * 100) : 0;

            let icon = '';
            if (labelKey === 'platform') {
                icon = getPlatformIcon(label);
            }

            html += `
                <div class="ppps-bar-item">
                    <div class="ppps-bar-label" title="${escapeHtml(label)}">
                        ${icon}${escapeHtml(truncate(label, 25))}
                    </div>
                    <div class="ppps-bar-wrapper">
                        <div class="ppps-bar-fill" style="width: ${percentage}%">
                            <span class="ppps-bar-count">${formatNumber(count)}</span>
                        </div>
                    </div>
                </div>
            `;
        });

        html += '</div>';

        $container.html(html);
    }

    /**
     * Get platform icon
     */
    function getPlatformIcon(platform) {
        const icons = {
            'Spotify': '🎵 ',
            'Apple Podcasts': '🎧 ',
            'Google Podcasts': '🔊 ',
            'Pocket Casts': '📱 ',
            'Amazon Music': '🎵 ',
            'Podimo': '🎧 ',
            'iVoox': '🎤 ',
            'Web/Browser': '🌐 ',
            'Other': '📊 ',
            'YouTube Music': '▶️ ',
            'Overcast': '☁️ ',
            'Castro': '📻 ',
            'Castbox': '📦 ',
            'Podcast Addict': '🎙️ ',
            'Player FM': '▶️ ',
            'Stitcher': '🎵 ',
            'TuneIn': '📻 ',
            'Deezer': '🎵 '
        };

        return icons[platform] || '📊 ';
    }

    /**
     * Truncate text
     */
    function truncate(text, maxLength) {
        if (text.length <= maxLength) return text;
        return text.substring(0, maxLength - 3) + '...';
    }

    /**
     * Format number
     */
    function formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }
});

