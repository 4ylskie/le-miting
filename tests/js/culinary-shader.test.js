import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import vm from 'node:vm';

test('the shader animates despite reduced-motion preference and pauses only through its explicit hook', async () => {
    const source = await readFile(new URL('../../public/js/culinary-shader.js', import.meta.url), 'utf8');
    const frames = new Map();
    let nextFrame = 0;
    let observerCallback;
    let framePixels;
    const canvas = {
        width: 0,
        height: 0,
        getContext: () => ({
            createImageData: (width, height) => ({ data: new Uint8ClampedArray(width * height * 4) }),
            putImageData: (imageData) => { framePixels = imageData.data.slice(); },
        }),
    };
    const document = {
        hidden: false,
        documentElement: { dataset: { shaderMotion: '' } },
        getElementById: () => canvas,
        addEventListener: () => {},
    };
    const window = {
        innerWidth: 480,
        innerHeight: 320,
        matchMedia: () => { throw new Error('Shader must not consult prefers-reduced-motion.'); },
        requestAnimationFrame: (callback) => {
            const frameId = ++nextFrame;
            frames.set(frameId, callback);

            return frameId;
        },
        cancelAnimationFrame: (frameId) => frames.delete(frameId),
        addEventListener: () => {},
    };
    function HTMLCanvasElement() {}

    HTMLCanvasElement.prototype = Object.getPrototypeOf(canvas);

    const context = {
        document,
        window,
        HTMLCanvasElement,
        MutationObserver: class MutationObserver {
            constructor(callback) { observerCallback = callback; }
            observe() {}
        },
        performance: { now: () => 0 },
        Math,
        Uint8ClampedArray,
    };

    vm.runInNewContext(source, context);
    assert.equal(frames.size, 1, 'an animation frame is scheduled on startup');

    const [firstFrameId, firstFrame] = frames.entries().next().value;
    frames.delete(firstFrameId);
    firstFrame(1000);
    const firstPixels = framePixels;

    assert.equal(frames.size, 1, 'animation continues with reduced motion enabled by the environment');

    const [secondFrameId, secondFrame] = frames.entries().next().value;
    frames.delete(secondFrameId);
    secondFrame(1400);
    assert.notDeepEqual(framePixels, firstPixels, 'later animation frames render different pixels');

    document.documentElement.dataset.shaderMotion = 'paused';
    observerCallback();
    assert.equal(frames.size, 0, 'the explicit paused hook cancels the scheduled frame');

    document.documentElement.dataset.shaderMotion = '';
    observerCallback();
    assert.equal(frames.size, 1, 'removing the pause hook resumes the animation');
});
