const DASHBOARD_METRICS = [
    'ordersToday',
    'reservationsToday',
    'revenueToday',
    'pendingOrders',
    'pendingReservations',
    'availableTables',
];

const REFRESH_INTERVAL = 30_000;

export function normalizeDashboardMetrics(snapshot) {
    if (snapshot === null || typeof snapshot !== 'object' || Array.isArray(snapshot)) {
        throw new Error('Invalid dashboard metrics.');
    }

    const metrics = {};

    for (const key of DASHBOARD_METRICS) {
        const value = snapshot[key];

        if (typeof value !== 'number' || !Number.isFinite(value) || value < 0) {
            throw new Error('Invalid dashboard metrics.');
        }

        if (key !== 'revenueToday' && !Number.isInteger(value)) {
            throw new Error('Invalid dashboard metrics.');
        }

        metrics[key] = value;
    }

    return metrics;
}

export function createDashboardPoller({
    requestMetrics,
    applyMetrics,
    setFailureState,
    isPageHidden,
    scheduleTimeout,
    cancelTimeout,
}) {
    let timeoutId = null;
    let requestInFlight = false;
    let stopped = false;

    function clearScheduledRefresh() {
        if (timeoutId !== null) {
            cancelTimeout(timeoutId);
            timeoutId = null;
        }
    }

    function scheduleNextRefresh() {
        if (stopped || isPageHidden()) {
            return;
        }

        clearScheduledRefresh();
        timeoutId = scheduleTimeout(() => {
            timeoutId = null;
            void refresh();
        }, REFRESH_INTERVAL);
    }

    async function refresh() {
        if (stopped || isPageHidden() || requestInFlight) {
            return;
        }

        clearScheduledRefresh();
        requestInFlight = true;

        try {
            const metrics = normalizeDashboardMetrics(await requestMetrics());
            applyMetrics(metrics);
            setFailureState(false);
        } catch {
            setFailureState(true);
        } finally {
            requestInFlight = false;
            scheduleNextRefresh();
        }
    }

    function visibilityChanged() {
        if (isPageHidden()) {
            clearScheduledRefresh();

            return;
        }

        void refresh();
    }

    function stop() {
        stopped = true;
        clearScheduledRefresh();
    }

    scheduleNextRefresh();

    return { refresh, visibilityChanged, stop };
}

export function startAdminDashboardRefresh(root, documentRef = document) {
    const summaryUrl = root.dataset.dashboardSummaryUrl;
    const status = root.querySelector('[data-dashboard-refresh-status]');
    const targets = new Map(
        [...root.querySelectorAll('[data-dashboard-metric]')]
            .map((element) => [element.dataset.dashboardMetric, element]),
    );

    if (!summaryUrl || !status || DASHBOARD_METRICS.some((key) => !targets.has(key))) {
        return () => {};
    }

    const requestMetrics = async () => {
        const response = await documentRef.defaultView.fetch(summaryUrl, {
            cache: 'no-store',
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            throw new Error('Dashboard summary request failed.');
        }

        return response.json();
    };

    const applyMetrics = (metrics) => {
        for (const key of DASHBOARD_METRICS) {
            const value = key === 'revenueToday'
                ? `RM ${new Intl.NumberFormat('en-MY', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                }).format(metrics[key])}`
                : String(metrics[key]);

            targets.get(key).textContent = value;
        }
    };

    const setFailureState = (failed) => {
        status.textContent = failed
            ? 'Dashboard metrics could not be refreshed. Showing the last successful values.'
            : '';
        status.hidden = !failed;
    };

    const poller = createDashboardPoller({
        requestMetrics,
        applyMetrics,
        setFailureState,
        isPageHidden: () => documentRef.hidden,
        scheduleTimeout: (callback, delay) => documentRef.defaultView.setTimeout(callback, delay),
        cancelTimeout: (timeoutId) => documentRef.defaultView.clearTimeout(timeoutId),
    });

    const onVisibilityChange = () => poller.visibilityChanged();
    documentRef.addEventListener('visibilitychange', onVisibilityChange);

    return () => {
        documentRef.removeEventListener('visibilitychange', onVisibilityChange);
        poller.stop();
    };
}

if (typeof document !== 'undefined') {
    document.querySelectorAll('[data-dashboard-refresh]').forEach((root) => {
        startAdminDashboardRefresh(root, document);
    });
}
