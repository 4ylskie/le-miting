import assert from 'node:assert/strict';
import test from 'node:test';
import {
    createDashboardPoller,
    normalizeDashboardMetrics,
    startAdminDashboardRefresh,
} from '../../resources/js/admin-dashboard-refresh.js';

const metrics = {
    ordersToday: 3,
    reservationsToday: 2,
    revenueToday: 125.5,
    pendingOrders: 4,
    pendingReservations: 1,
    availableTables: 6,
};

function createClock() {
    let nextId = 1;
    const timers = new Map();

    return {
        timers,
        schedule(callback, delay) {
            const id = nextId++;
            timers.set(id, { callback, delay });

            return id;
        },
        cancel(id) {
            timers.delete(id);
        },
    };
}

test('retains the last successful metrics and reports a refresh failure', async () => {
    let shouldFail = false;
    const displayed = [];
    const failures = [];
    const clock = createClock();
    const poller = createDashboardPoller({
        requestMetrics: async () => {
            if (shouldFail) {
                throw new Error('network unavailable');
            }

            return metrics;
        },
        applyMetrics: (value) => displayed.push(value),
        setFailureState: (failed) => failures.push(failed),
        isPageHidden: () => false,
        scheduleTimeout: clock.schedule,
        cancelTimeout: clock.cancel,
    });

    await poller.refresh();
    shouldFail = true;
    await poller.refresh();

    assert.deepEqual(displayed, [metrics]);
    assert.deepEqual(failures, [false, true]);

    poller.stop();
});

test('does not issue overlapping summary requests', async () => {
    let resolveRequest;
    let requestCount = 0;
    const clock = createClock();
    const poller = createDashboardPoller({
        requestMetrics: () => {
            requestCount++;

            return new Promise((resolve) => {
                resolveRequest = resolve;
            });
        },
        applyMetrics: () => {},
        setFailureState: () => {},
        isPageHidden: () => false,
        scheduleTimeout: clock.schedule,
        cancelTimeout: clock.cancel,
    });

    const firstRequest = poller.refresh();
    await poller.refresh();
    resolveRequest(metrics);
    await firstRequest;

    assert.equal(requestCount, 1);

    poller.stop();
});

test('pauses while hidden and resumes immediately when visible', async () => {
    let hidden = true;
    let requestCount = 0;
    const clock = createClock();
    const poller = createDashboardPoller({
        requestMetrics: async () => {
            requestCount++;

            return metrics;
        },
        applyMetrics: () => {},
        setFailureState: () => {},
        isPageHidden: () => hidden,
        scheduleTimeout: clock.schedule,
        cancelTimeout: clock.cancel,
    });

    assert.equal(clock.timers.size, 0);
    hidden = false;
    poller.visibilityChanged();
    await new Promise((resolve) => setImmediate(resolve));

    assert.equal(requestCount, 1);
    assert.deepEqual([...clock.timers.values()].map(({ delay }) => delay), [30_000]);

    poller.stop();
});

test('rejects an incomplete or malformed metric payload before applying it', () => {
    assert.throws(() => normalizeDashboardMetrics({ ...metrics, pendingOrders: '4' }));
    assert.throws(() => normalizeDashboardMetrics({ ...metrics, availableTables: Number.NaN }));
    assert.throws(() => normalizeDashboardMetrics({ ordersToday: 1 }));
});

test('updates metric text safely and keeps the last values when the endpoint fails', async () => {
    let shouldFail = false;
    let visibilityListener;
    const scheduled = [];
    const metricElements = Object.entries(metrics).map(([key]) => ({
        dataset: { dashboardMetric: key },
        textContent: `initial-${key}`,
    }));
    const status = { hidden: true, textContent: '' };
    const root = {
        dataset: { dashboardSummaryUrl: '/admin/dashboard/summary' },
        querySelector: () => status,
        querySelectorAll: () => metricElements,
    };
    const documentRef = {
        hidden: false,
        defaultView: {
            fetch: async (url, options) => {
                assert.equal(url, '/admin/dashboard/summary');
                assert.equal(options.credentials, 'same-origin');
                assert.equal(options.cache, 'no-store');
                assert.equal(options.headers.Accept, 'application/json');

                if (shouldFail) {
                    return { ok: false };
                }

                return { ok: true, json: async () => metrics };
            },
            setTimeout: (callback, delay) => {
                scheduled.push({ callback, delay });

                return scheduled.length;
            },
            clearTimeout: () => {},
        },
        addEventListener: (event, listener) => {
            assert.equal(event, 'visibilitychange');
            visibilityListener = listener;
        },
        removeEventListener: () => {},
    };

    const stop = startAdminDashboardRefresh(root, documentRef);
    visibilityListener();
    await new Promise((resolve) => setImmediate(resolve));

    assert.deepEqual(metricElements.map(({ textContent }) => textContent), [
        '3', '2', 'RM 125.50', '4', '1', '6',
    ]);
    assert.equal(status.hidden, true);

    shouldFail = true;
    visibilityListener();
    await new Promise((resolve) => setImmediate(resolve));

    assert.deepEqual(metricElements.map(({ textContent }) => textContent), [
        '3', '2', 'RM 125.50', '4', '1', '6',
    ]);
    assert.equal(status.hidden, false);
    assert.match(status.textContent, /last successful values/);
    assert.equal(scheduled.at(-1).delay, 30_000);

    stop();
});
