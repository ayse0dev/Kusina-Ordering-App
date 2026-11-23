import * as THREE from 'three';
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js';
import { RoomEnvironment } from 'three/examples/jsm/environments/RoomEnvironment.js';

const PX = 50;
const LEVEL_H = 3.4;
const WALL_H = 2.8;
const SLAB = 0.2;
const GOLD = 0xc9a45c;

const STATES = {
    idle:      { color: 0xe8dfca, emissive: 0x000000, ei: 0 },
    available: { color: 0x9ae6b4, emissive: 0x34d399, ei: 0.18 },
    booked:    { color: 0xdc2626, emissive: 0x7f1d1d, ei: 0.35 },
    small:     { color: 0x6b7280, emissive: 0x000000, ei: 0 },
    selected:  { color: 0x16a34a, emissive: 0x15803d, ei: 0.85 },
};

const isOutdoor = (name) => /patio|terrace|garden|outdoor|rooftop|deck/i.test(name || '');

function makeLabel(title, sub, wide = false, vip = false) {
    const c = document.createElement('canvas');
    c.width = wide ? 640 : 256; c.height = 128;
    const g = c.getContext('2d');
    const mid = c.width / 2;
    if (!wide) {
        g.fillStyle = vip ? 'rgba(74,50,6,0.88)' : 'rgba(10,10,9,0.72)';
        g.beginPath();
        g.roundRect ? g.roundRect(14, 6, c.width - 28, c.height - 12, 18) : g.rect(14, 6, c.width - 28, c.height - 12);
        g.fill();
        g.strokeStyle = vip ? '#fcd34d' : 'rgba(201,164,92,0.8)';
        g.lineWidth = vip ? 6 : 3;
        g.stroke();
    }
    g.textAlign = 'center';
    g.shadowColor = 'rgba(0,0,0,.85)';
    g.shadowBlur = 8;
    g.fillStyle = '#ffffff';
    g.font = (wide ? '700 64px' : '700 60px') + ' "Josefin Sans", "Segoe UI", sans-serif';
    g.fillText(title, mid, 78);
    g.shadowBlur = 5;
    g.fillStyle = '#e8d7ae';
    g.font = '400 28px "Lato", "Segoe UI", sans-serif';
    g.fillText(sub, mid, 114);
    const tex = new THREE.CanvasTexture(c);
    tex.colorSpace = THREE.SRGBColorSpace;
    const sprite = new THREE.Sprite(new THREE.SpriteMaterial({ map: tex, transparent: true, depthWrite: false }));
    sprite.scale.set(1.35, 0.675, 1);
    sprite.renderOrder = 20;
    return sprite;
}

export function createScene(container, floors, cb = {}) {
    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'high-performance' });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.05;
    container.appendChild(renderer.domElement);
    renderer.domElement.style.cssText = 'display:block;width:100%;height:100%;touch-action:none;outline:none';
    renderer.domElement.setAttribute('aria-label', '3D view of the restaurant. Drag to rotate, scroll to zoom, click a table to select it.');

    const scene = new THREE.Scene();
    const pmrem = new THREE.PMREMGenerator(renderer);
    const envTex = pmrem.fromScene(new RoomEnvironment(), 0.04).texture;
    scene.environment = envTex;
    scene.environmentIntensity = 0.55;

    const camera = new THREE.PerspectiveCamera(38, 1, 0.1, 200);
    const controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.dampingFactor = 0.08;
    controls.maxPolarAngle = Math.PI * 0.49;
    controls.minDistance = 4;
    controls.maxDistance = 70;
    controls.autoRotate = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    controls.autoRotateSpeed = 0.7;
    controls.screenSpacePanning = true;

    scene.add(new THREE.HemisphereLight(0xfff1d6, 0x1a1410, 0.55));
    const moon = new THREE.DirectionalLight(0x9db4ff, 0.35);
    moon.position.set(-12, 20, 10);
    scene.add(moon);

    const goldMat = new THREE.MeshStandardMaterial({ color: GOLD, metalness: 0.75, roughness: 0.35 });
    const woodMat = new THREE.MeshStandardMaterial({ color: 0x4a3322, roughness: 0.75, metalness: 0.05 });
    const slabMat = new THREE.MeshStandardMaterial({ color: 0x1b1a18, roughness: 0.6, metalness: 0.1 });
    const darkMat = new THREE.MeshStandardMaterial({ color: 0x23201c, roughness: 0.6, metalness: 0.3 });
    const leafMat = new THREE.MeshStandardMaterial({ color: 0x2f6b3f, roughness: 0.8 });
    const potMat = new THREE.MeshStandardMaterial({ color: 0x6b4a2b, roughness: 0.7 });
    const glassMat = new THREE.MeshPhysicalMaterial({
        color: 0xb5cfe0, transparent: true, opacity: 0.1, roughness: 0.04, metalness: 0,
        side: THREE.DoubleSide, depthWrite: false,
    });
    const lampMat = new THREE.MeshBasicMaterial({ color: 0xffd9a0 });
    const lineMat = new THREE.LineBasicMaterial({ color: GOLD, transparent: true, opacity: 0.85 });
    const frostMat = (color, opacity) => new THREE.MeshStandardMaterial({ color, transparent: true, opacity, roughness: 0.3, metalness: 0.1, depthWrite: false });
    const steelMat = new THREE.MeshStandardMaterial({ color: 0xa7adb3, metalness: 0.8, roughness: 0.3 });
    const ZONE_FLOOR = { kitchen: 0x383c40, bathroom: 0x2e3d44, office: 0x2d2723, vip: 0x5a1624, patio: 0x7a5a3a };
    const DOOR_H = 2.0;
    const SILL = 0.95, HEAD = 2.0;
    const paneMat = new THREE.MeshPhysicalMaterial({ color: 0xbfdcee, transparent: true, opacity: 0.3, roughness: 0.05, side: THREE.DoubleSide, depthWrite: false });

    const OUT = { top: [0, -1], bottom: [0, 1], left: [-1, 0], right: [1, 0] };
    const frameMat = new THREE.MeshStandardMaterial({ color: 0x16181b, metalness: 0.7, roughness: 0.35 });
    const modernGlass = new THREE.MeshPhysicalMaterial({ color: 0x9fc4d8, transparent: true, opacity: 0.35, roughness: 0.03, metalness: 0.1, side: THREE.DoubleSide, depthWrite: false });
    const curtainMat = new THREE.MeshStandardMaterial({ color: 0x7a2434, roughness: 0.95, side: THREE.DoubleSide });
    const blackCurtainMat = new THREE.MeshStandardMaterial({ color: 0x0c0c0e, roughness: 0.95, side: THREE.DoubleSide });
    const doors = [];
    const serve = { pending: [], seen: new Set(), trip: null };
    const actors = [];
    const screens = [];
    const LOOKS = {
        chef: { top: 0xf5f5f1, pants: 0x2b2f36, skin: 0xe0ac86, hat: true, apron: 0xffffff },
        waiter: { top: 0x101114, pants: 0x101114, skin: 0xd9a47e, hair: 0x1b1410, shirt: 0xffffff, bow: 0x9b1c1c, stripe: 0x2a2c31 },
        bartender: { top: 0xf1efe9, pants: 0x17181c, skin: 0xc68a63, hair: 0x2a1c12, vest: 0x1b1c20, bow: 0x1b1c20 },
        clerk: { top: 0x4a505c, pants: 0x2a2d34, skin: 0xf0c4a0, hair: 0x4a2f1a, tie: 0xc9a45c },
        guest: { top: 0x2e6f95, pants: 0x30343c, skin: 0xb98260, hair: 0x1b1410 },
    };

    const POSES = {
        idle: () => ({}),
        stir: (t) => ({ srx: -0.75 + Math.sin(t * 6) * 0.1, srz: Math.cos(t * 6) * 0.2, er: -0.9, slx: -0.55, el: -1.0, hx: 0.25 }),
        chop: (t) => ({ slx: -0.7, srx: -0.7, el: -0.8, er: -0.75 + Math.sin(t * 14) * 0.5, hx: 0.3 }),
        wash: (t) => ({ slx: -0.7, srx: -0.7, el: -0.85 + Math.sin(t * 5) * 0.2, er: -0.85 - Math.sin(t * 5) * 0.2, hx: 0.25 }),
        plate: (t) => ({ srx: -0.8, er: -0.9 + Math.sin(t * 3.5) * 0.3, slx: -0.5, el: -0.7, hx: 0.25 }),
        fridge: (t) => ({ srx: -1.3, er: -0.25 + Math.sin(t * 2) * 0.05, hx: 0 }),
        pickup: (t) => ({ slx: -0.7, srx: -0.7, el: -0.9 + Math.sin(t * 3) * 0.12, er: -0.9 + Math.sin(t * 3) * 0.12 }),
        serve: (t) => ({ slx: -0.6, srx: -0.6, el: -1.0 + Math.sin(t * 2.5) * 0.18, er: -1.0 + Math.sin(t * 2.5) * 0.18, hx: 0.1 }),
        type: (t) => ({ slx: -0.5, srx: -0.5, el: -1.05 + Math.sin(t * 11) * 0.09, er: -1.05 + Math.sin(t * 11 + 2) * 0.09, hx: 0.12 + Math.sin(t * 0.6) * 0.04 }),
        paper: (t) => ({ slx: -0.5, el: -1.1, srx: -0.55 + Math.sin(t * 2.2) * 0.2, srz: Math.sin(t * 2.2) * 0.35, er: -0.95, hx: 0.42 }),
        shake: (t) => ({ slx: -1.4, srx: -1.4, el: -1.35 + Math.sin(t * 17) * 0.33, er: -1.35 + Math.sin(t * 17) * 0.33, hx: -0.05 }),
        pour: (t) => ({ srx: -1.1, er: -0.9 - Math.min(1, (t % 4) / 2) * 0.4, slx: -0.75, el: -1.15, hx: 0.2 }),
        polish: (t) => ({ slx: -0.7, el: -1.25, srx: -0.7 + Math.sin(t * 5) * 0.1, er: -1.2, srz: Math.sin(t * 6) * 0.15, hx: 0.25 }),
        reach: (t) => ({ srx: -2.4 + Math.sin(t * 1.5) * 0.1, er: -0.15, slx: -0.2, hx: -0.25 }),
    };

    const turn = (from, to, k) => { let d = ((to - from + Math.PI) % (Math.PI * 2) + Math.PI * 2) % (Math.PI * 2) - Math.PI; return from + d * k; };

    const std = (color, rough = 0.7, metal = 0.05) => new THREE.MeshStandardMaterial({ color, roughness: rough, metalness: metal });
    const M = {
        white: std(0xf1efe9, 0.5), porcelain: std(0xf6f7f7, 0.2, 0.05), chrome: std(0xd2d7db, 0.18, 0.9),
        black: std(0x17181c, 0.45, 0.3), steelDark: std(0x707780, 0.35, 0.8), sofa: std(0x5c2f2c, 0.85), sofaDark: std(0x3d2321, 0.9),
        paper: std(0xfbfbf7, 0.9), wood2: std(0x8b6b4a, 0.7), fire: new THREE.MeshBasicMaterial({ color: 0xff7a1a }),
        redBottle: std(0x9b1c1c, 0.2), greenBottle: std(0x1f6b3a, 0.2), amberBottle: std(0xb8741a, 0.2), clearBottle: std(0xcfe3ea, 0.1),
        mirror: new THREE.MeshStandardMaterial({ color: 0xd8e8f0, metalness: 0.95, roughness: 0.06 }),
        glass: new THREE.MeshPhysicalMaterial({ color: 0xdff0f7, transparent: true, opacity: 0.45, roughness: 0.05 }),
        rug: std(0x3a3f55, 1), cabinet: std(0x5a4030, 0.7), tileLight: std(0xd3e0e5, 0.35),
    };

    const ground = new THREE.Mesh(
        new THREE.CircleGeometry(40, 64),
        new THREE.MeshStandardMaterial({ color: 0x14120f, roughness: 0.95, metalness: 0 }),
    );
    ground.rotation.x = -Math.PI / 2;
    ground.position.y = -0.02;
    scene.add(ground);
    const grid = new THREE.GridHelper(80, 80, 0x3a3224, 0x211d16);
    grid.position.y = 0;
    grid.material.transparent = true;
    grid.material.opacity = 0.35;
    scene.add(grid);

    const levels = [];
    const tables = new Map();
    const pickables = [];
    let maxW = 8, maxD = 6;

    const hasEnclosed = floors.some((f) => !isOutdoor(f.name));
    const mainDepth = (floors.find((f) => !isOutdoor(f.name))?.height ?? 0) / PX;
    let enclosedIdx = 0;
    let frontEdge = hasEnclosed ? mainDepth / 2 + 1.5 : 0;

    floors.forEach((floor) => {
        const W = floor.width / PX, D = floor.height / PX;
        const outdoor = isOutdoor(floor.name);
        const custom = !!(floor.outline && floor.outline.length >= 3);
        const poly = custom
            ? floor.outline.map(([x, y]) => [x / PX - W / 2, y / PX - D / 2])
            : [[-W / 2, -D / 2], [W / 2, -D / 2], [W / 2, D / 2], [-W / 2, D / 2]];
        const bbox = poly.reduce((b, [x, z]) => ({ x0: Math.min(b.x0, x), x1: Math.max(b.x1, x), z0: Math.min(b.z0, z), z1: Math.max(b.z1, z) }),
            { x0: Infinity, x1: -Infinity, z0: Infinity, z1: -Infinity });
        const zoneBox = (floor.zones || []).reduce((b, z) => ({
            x0: Math.min(b.x0, z.x / PX - W / 2), x1: Math.max(b.x1, (z.x + z.w) / PX - W / 2),
            z0: Math.min(b.z0, z.y / PX - D / 2), z1: Math.max(b.z1, (z.y + z.h) / PX - D / 2),
        }), bbox);
        const fw = zoneBox.x1 - zoneBox.x0, fd = zoneBox.z1 - zoneBox.z0;
        maxW = Math.max(maxW, fw);
        maxD = Math.max(maxD, fd);
        const group = new THREE.Group();
        let y = 0, cz = 0;
        if (outdoor) {
            cz = frontEdge + D / 2;
            frontEdge += D + 1.3;
        } else {
            y = enclosedIdx++ * LEVEL_H;
        }
        group.position.set(0, y, cz);
        scene.add(group);
        levels.push({ group, floor, outdoor, y, W: fw, D: fd, cx: (zoneBox.x0 + zoneBox.x1) / 2, cz: cz + (zoneBox.z0 + zoneBox.z1) / 2 });

        const shape = new THREE.Shape(poly.map(([x, z]) => new THREE.Vector2(x, -z)));
        const well = (floor.zones || []).find((q) => q.type === 'stairs' && q.dir === 'down');
        if (well) {
            const wx0 = well.x / PX - W / 2, wx1 = (well.x + well.w) / PX - W / 2, wz0 = well.y / PX - D / 2, wz1 = (well.y + well.h) / PX - D / 2;
            const pad = (wx1 - wx0) * 0.04, wzh = wz1 - 0.36 * (wz1 - wz0);
            shape.holes.push(new THREE.Path([new THREE.Vector2(wx0 + pad, -wz0), new THREE.Vector2(wx1 - pad, -wz0), new THREE.Vector2(wx1 - pad, -wzh), new THREE.Vector2(wx0 + pad, -wzh)]));
        }
        const prism = (depth) => {
            const g = new THREE.ExtrudeGeometry(shape, { depth, bevelEnabled: false });
            g.rotateX(-Math.PI / 2);
            return g;
        };
        const slab = new THREE.Mesh(prism(SLAB), slabMat);
        slab.position.y = -SLAB;
        group.add(slab);
        const floorTop = new THREE.Mesh(prism(0.04), outdoor ? darkMat : woodMat);
        group.add(floorTop);
        const slabEdge = new THREE.LineSegments(new THREE.EdgesGeometry(slab.geometry), lineMat);
        slabEdge.position.copy(slab.position);
        group.add(slabEdge);

        if (!outdoor) buildGlassShell(group, poly, { legacy: !custom, ground: y === 0, title: floor.name, W, D });
        else buildRailing(group, 0, 0, W, D);

        const lx = [bbox.x0 + (bbox.x1 - bbox.x0) * 0.28, bbox.x0 + (bbox.x1 - bbox.x0) * 0.72];
        const lz = [bbox.z0 + (bbox.z1 - bbox.z0) * 0.3, bbox.z0 + (bbox.z1 - bbox.z0) * 0.7];
        for (const px of lx) for (const pz of lz) {
            const l = new THREE.PointLight(0xffc98a, outdoor ? 14 : 16, 11, 1.6);
            l.position.set(px, WALL_H - 0.5, pz);
            group.add(l);
        }

        (floor.zones || []).forEach((z) => buildZone(group, floor, z));
        floor.tables.forEach((t) => buildTable(group, floor, t));
        if (!custom) buildPlants(group, W, D);
    });

    function buildGlassShell(group, poly, { legacy, ground, title, W, D }) {
        const H = WALL_H;
        const n = poly.length;
        const edges = poly.map((p, i) => [p, poly[(i + 1) % n]]);
        const lineMulls = [];
        const frameLines = [];

        for (const [[ax, az], [bx, bz]] of edges) {
            const len = Math.hypot(bx - ax, bz - az);
            const rot = -Math.atan2(bz - az, bx - ax);
            const mx = (ax + bx) / 2, mz = (az + bz) / 2;

            const m = new THREE.Mesh(new THREE.PlaneGeometry(len, H), glassMat);
            m.position.set(mx, H / 2, mz);
            m.rotation.y = rot;
            m.renderOrder = 10;
            group.add(m);

            const tr = new THREE.Mesh(new THREE.BoxGeometry(len, 0.04, 0.04), goldMat);
            tr.position.set(mx, H * 0.62, mz);
            tr.rotation.y = rot;
            group.add(tr);

            const steps = Math.max(1, Math.round(len / 2));
            for (let i = 1; i < steps; i++) {
                const t = i / steps;
                const px = ax + (bx - ax) * t, pz = az + (bz - az) * t;
                lineMulls.push(px, 0, pz, px, H, pz);
            }
            frameLines.push(ax, 0, az, bx, 0, bz, ax, H, az, bx, H, bz);
        }

        for (const [x, z] of poly) {
            const post = new THREE.Mesh(new THREE.BoxGeometry(0.1, H, 0.1), goldMat);
            post.position.set(x, H / 2, z);
            group.add(post);
        }
        const mull = new THREE.BufferGeometry();
        mull.setAttribute('position', new THREE.Float32BufferAttribute(lineMulls, 3));
        group.add(new THREE.LineSegments(mull, new THREE.LineBasicMaterial({ color: GOLD, transparent: true, opacity: 0.35 })));
        const frame = new THREE.BufferGeometry();
        frame.setAttribute('position', new THREE.Float32BufferAttribute(frameLines, 3));
        group.add(new THREE.LineSegments(frame, lineMat));

        const roof = new THREE.Mesh(new THREE.ShapeGeometry(new THREE.Shape(poly.map(([x, z]) => new THREE.Vector2(x, -z)))), glassMat);
        roof.rotation.x = -Math.PI / 2;
        roof.position.y = H;
        roof.renderOrder = 10;
        roof.userData.roof = true;
        group.add(roof);

        if (legacy) {
            const door = new THREE.Mesh(new THREE.BoxGeometry(1.5, 2.3, 0.06), new THREE.MeshStandardMaterial({ color: GOLD, metalness: 0.7, roughness: 0.4, transparent: true, opacity: 0.28 }));
            door.position.set(0, 1.15, D / 2 + 0.03);
            if (ground) group.add(door);
            const doorFrame = new THREE.LineSegments(new THREE.EdgesGeometry(door.geometry), new THREE.LineBasicMaterial({ color: 0xfcd34d }));
            doorFrame.position.copy(door.position);
            if (ground) group.add(doorFrame);
            const sign = makeLabel(ground ? 'ENTRANCE' : String(title).toUpperCase(), '', true);
            sign.scale.set(3.2, 0.64, 1);
            sign.position.set(0, 2.62, D / 2 + 0.25);
            group.add(sign);
        }
    }

    function rectOf(floor, z) {
        const W = floor.width / PX, D = floor.height / PX;
        const x0 = z.x / PX - W / 2, x1 = (z.x + z.w) / PX - W / 2;
        const z0 = z.y / PX - D / 2, z1 = (z.y + z.h) / PX - D / 2;
        return { x0, x1, z0, z1, cx: (x0 + x1) / 2, cz: (z0 + z1) / 2, w: x1 - x0, d: z1 - z0, W, D };
    }

    function wallSegment(group, ax, az, bx, bz, h, mat, rail = false) {
        const len = Math.hypot(bx - ax, bz - az);
        if (len < 0.05) return;
        const m = new THREE.Mesh(new THREE.BoxGeometry(len, h, 0.07), mat);
        m.position.set((ax + bx) / 2, h / 2, (az + bz) / 2);
        m.rotation.y = -Math.atan2(bz - az, bx - ax);
        m.renderOrder = 9;
        group.add(m);
        if (rail) {
            const r = new THREE.Mesh(new THREE.BoxGeometry(len, 0.05, 0.1), goldMat);
            r.position.set((ax + bx) / 2, h, (az + bz) / 2);
            r.rotation.y = m.rotation.y;
            group.add(r);
        }
    }

    function wallBand(group, ax, az, bx, bz, y0, y1, mat) {
        const len = Math.hypot(bx - ax, bz - az);
        if (len < 0.03 || y1 - y0 < 0.02) return;
        const m = new THREE.Mesh(new THREE.BoxGeometry(len, y1 - y0, 0.1), mat);
        m.position.set((ax + bx) / 2, (y0 + y1) / 2, (az + bz) / 2);
        m.rotation.y = -Math.atan2(bz - az, bx - ax);
        group.add(m);
    }

    function solidAround(group, ax, az, bx, bz, h, mat, openings) {
        const len = Math.hypot(bx - ax, bz - az);
        const ux = (bx - ax) / len, uz = (bz - az) / len;
        const at = (t) => [ax + ux * t, az + uz * t];
        let t = 0;
        for (const [o0, o1] of openings) {
            const [x0, z0] = at(t), [x1, z1] = at(o0);
            wallBand(group, x0, z0, x1, z1, 0, h, mat);
            t = o1;
        }
        const [x0, z0] = at(t);
        wallBand(group, x0, z0, bx, bz, 0, h, mat);
        return { ux, uz, at, len };
    }

    function modernWindows(group, ax, az, bx, bz, h, mat) {
        const len = Math.hypot(bx - ax, bz - az);
        const n = Math.max(2, Math.round((len - 0.3) / 0.95));
        const margin = 0.15, gap = 0.07;
        const w = (len - margin * 2 - gap * (n - 1)) / n;
        const y0 = 1.45, y1 = 2.2;
        const ops = [];
        for (let i = 0; i < n; i++) ops.push([margin + i * (w + gap), margin + i * (w + gap) + w]);
        const { ux, uz, at } = solidAround(group, ax, az, bx, bz, h, mat, ops);
        const rot = -Math.atan2(bz - az, bx - ax);
        for (const [o0, o1] of ops) {
            const [x0, z0] = at(o0), [x1, z1] = at(o1);
            wallBand(group, x0, z0, x1, z1, 0, y0, mat);
            wallBand(group, x0, z0, x1, z1, y1, h, mat);
            const [cx, cz] = at((o0 + o1) / 2);
            const pane = new THREE.Mesh(new THREE.PlaneGeometry(o1 - o0, y1 - y0), modernGlass);
            pane.position.set(cx, (y0 + y1) / 2, cz);
            pane.rotation.y = rot;
            pane.renderOrder = 11;
            group.add(pane);
            const bar = (bw, bh, y, dx) => {
                const m = new THREE.Mesh(new THREE.BoxGeometry(bw, bh, 0.12), frameMat);
                m.position.set(cx + ux * dx, y, cz + uz * dx);
                m.rotation.y = rot;
                group.add(m);
            };
            bar(o1 - o0 + 0.04, 0.04, y0, 0);
            bar(o1 - o0 + 0.04, 0.04, y1, 0);
            bar(0.04, y1 - y0, (y0 + y1) / 2, -(o1 - o0) / 2);
            bar(0.04, y1 - y0, (y0 + y1) / 2, (o1 - o0) / 2);
        }
    }

    function tallWindows(group, ax, az, bx, bz, h, mat, side, drape = curtainMat) {
        const len = Math.hypot(bx - ax, bz - az);
        if (len < 2.2) { wallBand(group, ax, az, bx, bz, 0, h, mat); return; }
        const n = Math.max(1, Math.floor(len / 2.5));
        const ww = 1.25, y0 = 0.15, y1 = 2.4;
        const ops = [];
        for (let i = 0; i < n; i++) { const c = ((i + 0.5) * len) / n; ops.push([c - ww / 2, c + ww / 2]); }
        const { ux, uz, at } = solidAround(group, ax, az, bx, bz, h, mat, ops);
        const rot = -Math.atan2(bz - az, bx - ax);
        const [nx, nz] = OUT[side];
        const ix = -nx, iz = -nz;
        for (const [o0, o1] of ops) {
            const [x0, z0] = at(o0), [x1, z1] = at(o1);
            wallBand(group, x0, z0, x1, z1, 0, y0, mat);
            wallBand(group, x0, z0, x1, z1, y1, h, mat);
            const [cx, cz] = at((o0 + o1) / 2);
            const pane = new THREE.Mesh(new THREE.PlaneGeometry(ww, y1 - y0), modernGlass);
            pane.position.set(cx, (y0 + y1) / 2, cz);
            pane.rotation.y = rot;
            pane.renderOrder = 11;
            group.add(pane);
            for (const [bw, bh, y, dx] of [[ww + 0.08, 0.06, y0, 0], [ww + 0.08, 0.06, y1, 0], [0.06, y1 - y0, (y0 + y1) / 2, -ww / 2], [0.06, y1 - y0, (y0 + y1) / 2, ww / 2], [0.04, y1 - y0, (y0 + y1) / 2, 0]]) {
                const m = new THREE.Mesh(new THREE.BoxGeometry(bw, bh, 0.12), frameMat);
                m.position.set(cx + ux * dx, y, cz + uz * dx);
                m.rotation.y = rot;
                group.add(m);
            }
            const rodLen = ww + 1.5;
            const rod = new THREE.Mesh(new THREE.CylinderGeometry(0.018, 0.018, rodLen, 8), goldMat);
            rod.rotation.z = Math.PI / 2;
            const rodG = new THREE.Group();
            rodG.add(rod);
            rodG.position.set(cx + ix * 0.2, 2.55, cz + iz * 0.2);
            rodG.rotation.y = rot;
            group.add(rodG);
            const panelW = 0.62, pleats = 7;
            for (const dir of [-1, 1]) {
                const start = dir * (ww / 2 + 0.05);
                for (let k = 0; k < pleats; k++) {
                    const off = start + dir * ((k + 0.5) * panelW) / pleats;
                    const fold = (k % 2 ? 1 : -1) * 0.035;
                    const m = new THREE.Mesh(new THREE.BoxGeometry(panelW / pleats + 0.01, 2.45, 0.03), drape);
                    m.position.set(cx + ux * off + ix * (0.2 + fold), 1.3, cz + uz * off + iz * (0.2 + fold));
                    m.rotation.y = rot;
                    group.add(m);
                }
            }
        }
    }

    function windowWall(group, ax, az, bx, bz, h, mat, kind, side) {
        if (kind === 'tall') tallWindows(group, ax, az, bx, bz, h, mat, side);
        else if (kind === 'tallBlack') tallWindows(group, ax, az, bx, bz, h, mat, side, blackCurtainMat);
        else modernWindows(group, ax, az, bx, bz, h, mat);
    }

    function doorway(group, r, z, h, doorMat, signText) {
        const gap = z.door;
        const axis = gap.side === 'top' || gap.side === 'bottom' ? 'x' : 'z';
        const off = (axis === 'x' ? r.W : r.D) / 2;
        const g0 = gap.from / PX - off, g1 = gap.to / PX - off;
        const wallPos = gap.side === 'top' ? r.z0 : gap.side === 'bottom' ? r.z1 : gap.side === 'left' ? r.x0 : r.x1;
        const [nx, nz] = OUT[gap.side];
        const P = (t) => (axis === 'x' ? [t, wallPos] : [wallPos, t]);
        const width = g1 - g0;

        const [lx0, lz0] = P(g0), [lx1, lz1] = P(g1);
        wallBand(group, lx0, lz0, lx1, lz1, DOOR_H, h, doorMat.wall);

        for (const t of [g0, g1]) {
            const [px, pz] = P(t);
            const m = new THREE.Mesh(new THREE.BoxGeometry(axis === 'x' ? 0.07 : 0.14, DOOR_H, axis === 'x' ? 0.14 : 0.07), goldMat);
            m.position.set(px, DOOR_H / 2, pz);
            group.add(m);
        }
        const head = new THREE.Mesh(new THREE.BoxGeometry(axis === 'x' ? width : 0.14, 0.07, axis === 'x' ? 0.14 : width), goldMat);
        head.position.set((lx0 + lx1) / 2, DOOR_H, (lz0 + lz1) / 2);
        group.add(head);

        const [hx, hz] = P(g0 + 0.04);
        const pivot = new THREE.Group();
        pivot.position.set(hx, 0, hz);
        const leaf = new THREE.Mesh(new THREE.BoxGeometry(width - 0.1, DOOR_H - 0.04, 0.05), doorMat.leaf);
        leaf.position.set((width - 0.1) / 2, (DOOR_H - 0.04) / 2, 0);
        pivot.add(leaf);
        const knob = new THREE.Mesh(new THREE.SphereGeometry(0.045, 10, 8), goldMat);
        knob.position.set(width - 0.22, 1.0, 0.045);
        pivot.add(knob);
        group.add(pivot);
        const door = { pivot, axis, nx, nz, cx: (lx0 + lx1) / 2, cz: (lz0 + lz1) / 2, angle: 0, dir: 1 };
        doors.push(door);
        poseDoor(door, 0, 1);

        if (signText) {
            const sign = makeLabel(String(signText).toUpperCase(), '', true);
            sign.scale.set(1.5, 0.3, 1);
            const [sx, sz] = P((g0 + g1) / 2);
            sign.position.set(sx + nx * 0.12, (DOOR_H + h) / 2 + 0.02, sz + nz * 0.12);
            sign.renderOrder = 12;
            group.add(sign);
        }
        return door;
    }

    function poseDoor(d, alpha, dir) {
        const sa = Math.sin(alpha), ca = Math.cos(alpha);
        d.pivot.rotation.y = d.axis === 'x' ? Math.atan2(-d.nz * dir * sa, ca) : Math.atan2(-ca, d.nx * dir * sa);
    }

    function cyl(group, rt, rb, h, x, y, z, mat, seg = 16) {
        const m = new THREE.Mesh(new THREE.CylinderGeometry(rt, rb, h, seg), mat);
        m.position.set(x, y, z);
        group.add(m);
        return m;
    }

    function plant(group, x, z, sc = 1) {
        cyl(group, 0.2 * sc, 0.15 * sc, 0.36 * sc, x, 0.18 * sc, z, potMat);
        for (let i = 0; i < 4; i++) {
            const l = new THREE.Mesh(new THREE.SphereGeometry(0.2 * sc, 10, 8), leafMat);
            l.position.set(x + Math.cos(i * 1.7) * 0.1 * sc, (0.52 + i * 0.13) * sc, z + Math.sin(i * 1.7) * 0.1 * sc);
            group.add(l);
        }
    }

    function sofa(group, x, z, len, phi, mat = M.sofa) {
        const g = new THREE.Group();
        g.position.set(x, 0, z);
        g.rotation.y = phi;
        box(g, len, 0.32, 0.86, 0, 0.2, 0, mat);
        const n = len > 1.5 ? 3 : (len > 1.1 ? 2 : 1);
        const cw = (len - 0.4) / n;
        for (let i = 0; i < n; i++) box(g, cw - 0.03, 0.14, 0.66, -len / 2 + 0.2 + (i + 0.5) * cw, 0.43, 0.08, M.sofaDark);
        box(g, len, 0.52, 0.2, 0, 0.62, -0.33, mat);
        box(g, 0.2, 0.52, 0.86, -len / 2 + 0.1, 0.4, 0, mat);
        box(g, 0.2, 0.52, 0.86, len / 2 - 0.1, 0.4, 0, mat);
        group.add(g);
        return g;
    }

    function officeChair(group, x, z, phi) {
        const g = new THREE.Group();
        g.position.set(x, 0, z);
        g.rotation.y = phi;
        box(g, 0.5, 0.08, 0.5, 0, 0.5, 0, M.black);
        box(g, 0.48, 0.52, 0.07, 0, 0.82, -0.24, M.black);
        cyl(g, 0.04, 0.04, 0.4, 0, 0.27, 0, M.chrome, 8);
        box(g, 0.6, 0.04, 0.07, 0, 0.07, 0, M.chrome);
        box(g, 0.07, 0.04, 0.6, 0, 0.07, 0, M.chrome);
        group.add(g);
    }

    function bottle(group, x, y, z, mat, hgt = 0.26) {
        cyl(group, 0.04, 0.04, hgt, x, y + hgt / 2, z, mat, 8);
        cyl(group, 0.016, 0.026, hgt * 0.35, x, y + hgt + hgt * 0.15, z, mat, 8);
    }

    function screenPlane(group, w, h, tex, x, y, z, phi) {
        const m = new THREE.Mesh(new THREE.PlaneGeometry(w, h), new THREE.MeshBasicMaterial({ map: tex, toneMapped: false }));
        m.position.set(x, y, z);
        m.rotation.y = phi;
        group.add(m);
        return m;
    }

    function canvasTexture(w, h) {
        const canvas = document.createElement('canvas');
        canvas.width = w; canvas.height = h;
        const tex = new THREE.CanvasTexture(canvas);
        tex.colorSpace = THREE.SRGBColorSpace;
        return { canvas, ctx: canvas.getContext('2d'), tex };
    }

    function makeKitchenMonitor() {
        const { canvas, ctx, tex } = canvasTexture(640, 360);
        const dishes = ['Chicken Adobo', 'Pancit Canton', 'Sinigang', 'Lechon Kawali', 'Kare-Kare', 'Crispy Pata', 'Sisig', 'Lumpia', 'Garlic Rice', 'Bangus', 'Halo-Halo', 'Leche Flan'];
        const tickets = [];
        let n = 4180, acc = 99, tick = 0;
        const spawn = () => {
            n += 1 + Math.floor(Math.random() * 3);
            const lines = [];
            for (let i = 0, c = 1 + Math.floor(Math.random() * 3); i < c; i++) lines.push(`${1 + Math.floor(Math.random() * 3)}× ${dishes[Math.floor(Math.random() * dishes.length)]}`);
            tickets.push({ code: `KUS-${n}`, lines, age: 0, status: 0 });
            if (tickets.length > 4) tickets.shift();
        };
        const COLORS = ['#34d399', '#fbbf24', '#60a5fa'], LABELS = ['NEW', 'COOKING', 'READY'];
        const draw = () => {
            ctx.fillStyle = '#0b0f14'; ctx.fillRect(0, 0, 640, 360);
            ctx.fillStyle = '#c9a45c'; ctx.fillRect(0, 0, 640, 44);
            ctx.fillStyle = '#0b0f14'; ctx.font = 'bold 22px Arial'; ctx.textBaseline = 'middle';
            ctx.fillText('KITCHEN · INCOMING ORDERS', 16, 23);
            ctx.textAlign = 'right'; ctx.fillText(new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }), 624, 23); ctx.textAlign = 'left';
            tickets.forEach((t, i) => {
                const x = 12 + i * 155, y = 58;
                ctx.fillStyle = '#161d26'; ctx.fillRect(x, y, 147, 286);
                ctx.fillStyle = COLORS[t.status]; ctx.fillRect(x, y, 147, 6);
                ctx.fillStyle = '#fff'; ctx.font = 'bold 20px Arial'; ctx.fillText(t.code, x + 10, y + 28);
                ctx.fillStyle = COLORS[t.status]; ctx.font = 'bold 15px Arial'; ctx.fillText(LABELS[t.status], x + 10, y + 52);
                ctx.fillStyle = '#d7dde5'; ctx.font = '17px Arial';
                t.lines.forEach((l, k) => ctx.fillText(l.length > 15 ? l.slice(0, 14) + '…' : l, x + 10, y + 86 + k * 28));
            });
            tex.needsUpdate = true;
        };
        screens.push({
            update(dt) {
                acc += dt;
                if (acc < 1) return;
                acc = 0; tick++;
                tickets.forEach((t) => { t.age++; if (t.age % 6 === 0 && t.status < 2) t.status++; });
                if (tick % 5 === 0) spawn();
                if (tickets.length && tickets[0].status === 2 && tickets[0].age > 20) tickets.shift();
                draw();
            },
        });
        return tex;
    }

    function buildPerson(kind) {
        const c = LOOKS[kind];
        const g = new THREE.Group();
        const part = (parent, geo, mat, x, y, z) => { const m = new THREE.Mesh(geo, mat); m.position.set(x, y, z); parent.add(m); return m; };
        const topMat = std(c.top, 0.7), pantMat = std(c.pants, 0.7), skinMat = std(c.skin, 0.8);

        part(g, new THREE.BoxGeometry(0.42, 0.56, 0.23), topMat, 0, 1.17, 0);
        part(g, new THREE.CylinderGeometry(0.045, 0.05, 0.08, 8), skinMat, 0, 1.47, 0);
        const head = new THREE.Group();
        head.position.set(0, 1.6, 0);
        part(head, new THREE.SphereGeometry(0.115, 14, 12), skinMat, 0, 0, 0);
        g.add(head);
        if (c.hat) {
            part(head, new THREE.CylinderGeometry(0.13, 0.12, 0.18, 14), std(0xffffff, 0.8), 0, 0.2, 0);
            part(head, new THREE.SphereGeometry(0.165, 12, 10), std(0xffffff, 0.8), 0, 0.3, 0);
        } else if (c.hair) {
            const hair = part(head, new THREE.SphereGeometry(0.123, 12, 10, 0, Math.PI * 2, 0, Math.PI * 0.55), std(c.hair, 0.9), 0, 0.02, -0.005);
            hair.rotation.x = -0.2;
        }
        for (const sx of [-1, 1]) part(head, new THREE.SphereGeometry(0.014, 6, 6), std(0x111111), sx * 0.04, 0.02, 0.108);

        if (c.shirt) {
            part(g, new THREE.BoxGeometry(0.16, 0.42, 0.02), std(c.shirt, 0.5), 0, 1.2, 0.118);
            for (const sx of [-1, 1]) { const l = part(g, new THREE.BoxGeometry(0.07, 0.4, 0.025), std(0x050506, 0.3), sx * 0.1, 1.2, 0.122); l.rotation.z = -sx * 0.18; }
            for (const sx of [-1, 1]) part(g, new THREE.BoxGeometry(0.07, 0.055, 0.03), std(c.bow, 0.5), sx * 0.04, 1.4, 0.128);
            part(g, new THREE.BoxGeometry(0.03, 0.03, 0.035), std(c.bow, 0.5), 0, 1.4, 0.13);
            for (let i = 0; i < 3; i++) part(g, new THREE.SphereGeometry(0.012, 6, 6), std(0xc9a45c, 0.3, 0.8), 0, 1.3 - i * 0.1, 0.132);
        }
        if (c.vest) {
            part(g, new THREE.BoxGeometry(0.43, 0.4, 0.245), std(c.vest, 0.6), 0, 1.12, 0);
            part(g, new THREE.BoxGeometry(0.1, 0.045, 0.03), std(c.bow, 0.5), 0, 1.42, 0.125);
        }
        if (c.apron) part(g, new THREE.BoxGeometry(0.4, 0.5, 0.02), std(c.apron, 0.8), 0, 0.98, 0.125);
        if (c.tie) part(g, new THREE.BoxGeometry(0.06, 0.3, 0.02), std(c.tie, 0.4), 0, 1.2, 0.122);

        const limb = (parent, w, len, mat, x, y, tip) => {
            const piv = new THREE.Group();
            piv.position.set(x, y, 0);
            part(piv, new THREE.BoxGeometry(w, len, w), mat, 0, -len / 2, 0);
            if (tip) part(piv, tip.geo, tip.mat, 0, -len, tip.z || 0);
            parent.add(piv);
            return piv;
        };
        const shoe = { geo: new THREE.BoxGeometry(0.15, 0.07, 0.26), mat: std(0x0d0d0f, 0.4), z: 0.05 };
        const hand = { geo: new THREE.SphereGeometry(0.05, 8, 8), mat: skinMat };
        const u = g.userData;
        u.hipL = limb(g, 0.17, 0.46, pantMat, -0.1, 0.9);
        u.hipR = limb(g, 0.17, 0.46, pantMat, 0.1, 0.9);
        u.kneeL = limb(u.hipL, 0.15, 0.46, pantMat, 0, -0.46, shoe);
        u.kneeR = limb(u.hipR, 0.15, 0.46, pantMat, 0, -0.46, shoe);
        u.shL = limb(g, 0.12, 0.3, topMat, -0.27, 1.4);
        u.shR = limb(g, 0.12, 0.3, topMat, 0.27, 1.4);
        u.elL = limb(u.shL, 0.1, 0.29, topMat, 0, -0.3, hand);
        u.elR = limb(u.shR, 0.1, 0.29, topMat, 0, -0.3, hand);
        u.head = head;

        const tray = new THREE.Group();
        tray.position.set(0, 1.0, 0.42);
        part(tray, new THREE.CylinderGeometry(0.24, 0.24, 0.02, 20), std(0xb9bec4, 0.25, 0.9), 0, 0, 0);
        part(tray, new THREE.CylinderGeometry(0.1, 0.09, 0.025, 16), std(0xffffff, 0.3), -0.08, 0.025, 0.02);
        part(tray, new THREE.CylinderGeometry(0.075, 0.07, 0.02, 16), std(0xffffff, 0.3), 0.1, 0.02, -0.04);
        part(tray, new THREE.SphereGeometry(0.06, 10, 8, 0, Math.PI * 2, 0, Math.PI / 2), std(0xc9a45c, 0.3, 0.7), -0.08, 0.04, 0.02);
        part(tray, new THREE.CylinderGeometry(0.03, 0.025, 0.1, 10), M.glass, 0.12, 0.06, 0.1);
        tray.visible = false;
        g.add(tray);
        u.tray = tray;
        return g;
    }

    function addActor(group, kind, [x, z], script, { speed = 1.1, seat = null, face = 0, next = null } = {}) {
        const mesh = buildPerson(kind);
        mesh.position.set(x, 0, z);
        mesh.rotation.y = face;
        group.add(mesh);
        actors.push({ kind, mesh, script, next, alt: 0, i: 0, t: 0, x, z, vx: 0, vz: 0, rot: face, faceTo: face, speed, seat, action: 'idle', moving: false, phase: 0, tray: false, pose: {} });
    }

    function updateActors(dt) {
        for (const sc of screens) sc.update(dt);

        for (const a of actors) {
            a.moving = false;
            for (let guard = 0; guard < 6; guard++) {
                if (a.next && a.i >= a.script.length) {
                    a.script = a.next() || [['do', 'idle', 0.4]];
                    a.i = 0;
                    a.t = 0;
                }
                const st = a.script[a.i % a.script.length];
                if (st[0] === 'tray') { a.tray = st[1]; a.i++; continue; }
                if (st[0] === 'call') { st[1](); a.i++; continue; }
                if (st[0] === 'hide' || st[0] === 'show') { a.mesh.visible = st[0] === 'show'; a.i++; continue; }
                if (st[0] === 'go') {
                    const dx = st[1] - a.x, dz = st[2] - a.z, d = Math.hypot(dx, dz);
                    if (d < 0.06) { a.i++; continue; }
                    const step = Math.min(d, a.speed * dt);
                    a.vx = dx / d; a.vz = dz / d;
                    a.x += a.vx * step; a.z += a.vz * step;
                    if (st[3] !== undefined) a.alt += (st[3] - a.alt) * (step / d);
                    a.faceTo = Math.atan2(a.vx, a.vz);
                    a.moving = true;
                    a.action = 'walk';
                } else {
                    a.action = st[1];
                    if (st[3] !== undefined) a.faceTo = st[3];
                    a.t += dt;
                    if (a.t >= st[2]) { a.t = 0; a.i++; }
                    a.vx = 0; a.vz = 0;
                }
                break;
            }
            a.rot = turn(a.rot, a.faceTo, Math.min(1, dt * 9));
            a.mesh.position.x = a.x;
            a.mesh.position.z = a.z;
            a.mesh.rotation.y = a.rot;

            const u = a.mesh.userData;
            const seated = !!a.seat && !a.moving;
            let tg;
            if (a.moving) {
                a.phase += dt * 7.2 * (a.speed / 1.1);
                const sw = Math.sin(a.phase) * 0.62;
                tg = { hl: sw, hr: -sw, kl: Math.max(0, -Math.sin(a.phase)) * 0.75, kr: Math.max(0, Math.sin(a.phase)) * 0.75, slx: -sw * 0.7, srx: sw * 0.7, el: -0.12, er: -0.12 };
                if (a.tray) Object.assign(tg, { slx: -0.6, srx: -0.6, el: -1.0, er: -1.0 });
            } else {
                tg = (POSES[a.action] || POSES.idle)(performance.now() / 1000);
            }
            if (seated) Object.assign(tg, { hl: -1.5, hr: -1.5, kl: 1.5, kr: 1.5 });
            const P = a.pose;
            const k = Math.min(1, dt * 10);
            const ease = (key, val = 0) => { P[key] = (P[key] ?? 0) + (val - (P[key] ?? 0)) * k; return P[key]; };
            u.hipL.rotation.x = ease('hl', tg.hl); u.hipR.rotation.x = ease('hr', tg.hr);
            u.kneeL.rotation.x = ease('kl', tg.kl); u.kneeR.rotation.x = ease('kr', tg.kr);
            u.shL.rotation.x = ease('slx', tg.slx); u.shR.rotation.x = ease('srx', tg.srx);
            u.shL.rotation.z = ease('slz', tg.slz); u.shR.rotation.z = ease('srz', tg.srz);
            u.elL.rotation.x = ease('el', tg.el); u.elR.rotation.x = ease('er', tg.er);
            u.head.rotation.x = ease('hx', tg.hx);
            const bob = a.moving ? Math.abs(Math.cos(a.phase)) * 0.03 : 0;
            a.mesh.position.y = ease('y', seated ? -0.4 : bob) + a.alt;
            u.tray.visible = a.tray;
        }

        for (const d of doors) {
            let target = 0;
            for (const a of actors) {
                if (!a.moving) continue;
                const px = a.x - d.cx, pz = a.z - d.cz;
                const dn = px * d.nx + pz * d.nz, lat = Math.abs(d.axis === 'x' ? px : pz);
                if (lat > 0.65 || Math.abs(a.vx * d.nx + a.vz * d.nz) < 0.6) continue;
                const prox = Math.max(0, Math.min(1, 1 - (Math.abs(dn) - 0.25) / 1.35));
                if (prox * 1.5 > target) {
                    target = prox * 1.5;
                    if (d.angle < 0.06) d.dir = Math.sign(a.vx * d.nx + a.vz * d.nz) || 1;
                }
            }
            d.angle += (target - d.angle) * Math.min(1, dt * 5);
            poseDoor(d, d.angle, d.dir);
        }
    }

    function solidRoom(group, floor, z, r, { color, leaf, windows = {}, sign }) {
        const h = WALL_H * 0.92;
        const mat = new THREE.MeshStandardMaterial({ color, roughness: 0.85, metalness: 0.02 });
        const gap = z.door;
        const sides = [
            ['top', r.x0, r.z0, r.x1, r.z0, 'x'], ['bottom', r.x0, r.z1, r.x1, r.z1, 'x'],
            ['left', r.x0, r.z0, r.x0, r.z1, 'z'], ['right', r.x1, r.z0, r.x1, r.z1, 'z'],
        ];
        for (const [side, ax, az, bx, bz, axis] of sides) {
            const draw = (x0, z0, x1, z1) => {
                if (windows[side]) windowWall(group, x0, z0, x1, z1, h, mat, windows[side], side);
                else wallBand(group, x0, z0, x1, z1, 0, h, mat);
            };
            if (gap && gap.side === side) {
                const off = (axis === 'x' ? r.W : r.D) / 2;
                const g0 = gap.from / PX - off, g1 = gap.to / PX - off;
                if (axis === 'x') { draw(ax, az, Math.max(ax, g0), bz); draw(Math.min(bx, g1), az, bx, bz); }
                else { draw(ax, az, bx, Math.max(az, g0)); draw(ax, Math.min(bz, g1), bx, bz); }
            } else {
                draw(ax, az, bx, bz);
            }
        }
        return gap ? doorway(group, r, z, h, { wall: mat, leaf: new THREE.MeshStandardMaterial({ color: leaf, roughness: 0.6 }) }, sign) : null;
    }

    function partitions(group, r, z, mat, h, rail) {
        const gap = z.door;
        const sides = [
            ['top', r.x0, r.z0, r.x1, r.z0, 'x'], ['bottom', r.x0, r.z1, r.x1, r.z1, 'x'],
            ['left', r.x0, r.z0, r.x0, r.z1, 'z'], ['right', r.x1, r.z0, r.x1, r.z1, 'z'],
        ];
        for (const [side, ax, az, bx, bz, axis] of sides) {
            if (gap && gap.side === side) {
                const off = (axis === 'x' ? r.W : r.D) / 2;
                const g0 = gap.from / PX - off, g1 = gap.to / PX - off;
                if (axis === 'x') {
                    wallSegment(group, ax, az, Math.max(ax, g0), bz, h, mat, rail);
                    wallSegment(group, Math.min(bx, g1), az, bx, bz, h, mat, rail);
                } else {
                    wallSegment(group, ax, az, bx, Math.max(az, g0), h, mat, rail);
                    wallSegment(group, ax, Math.min(bz, g1), bx, bz, h, mat, rail);
                }
            } else {
                wallSegment(group, ax, az, bx, bz, h, mat, rail);
            }
        }
    }

    function patch(group, r, color, y = 0.045) {
        const m = new THREE.Mesh(new THREE.BoxGeometry(r.w, 0.02, r.d), new THREE.MeshStandardMaterial({ color, roughness: 0.85 }));
        m.position.set(r.cx, y, r.cz);
        group.add(m);
        return m;
    }

    function zoneLabel(group, r, z, y = 1.9) {
        if (!z.label) return;
        const sw = Math.min(3.4, Math.max(1.5, Math.min(r.w, r.d) * 1.1));
        const s = makeLabel(String(z.label).toUpperCase(), z.note || '', true);
        s.scale.set(sw, sw * 0.2, 1);
        s.position.set(r.cx, y, r.cz);
        group.add(s);
    }

    function box(group, w, h, d, x, y, z, mat) {
        const m = new THREE.Mesh(new THREE.BoxGeometry(w, h, d), mat);
        m.position.set(x, y, z);
        group.add(m);
        return m;
    }

    function openStairs(group, r) {
        const n = 16, rise = LEVEL_H, run = r.d, stepD = run / n, w = r.w * 0.9;
        for (let i = 0; i < n; i++) {
            const h = ((i + 1) * rise) / n;
            box(group, w, 0.07, stepD * 1.06, r.cx, h - 0.035, r.z1 - (i + 0.5) * stepD, i === n - 1 ? goldMat : woodMat);
        }
        const theta = Math.atan2(rise, run), len = Math.hypot(run, rise);
        for (const sx of [-1, 1]) {
            const x = r.cx + sx * (w / 2 - 0.04);
            const str = box(group, 0.08, 0.34, len, x, rise / 2 - 0.13, r.cz, M.steelDark);
            str.rotation.x = theta;
            for (const f of [0.22, 0.5, 0.78, 0.96]) {
                const hh = f * rise - 0.2;
                if (hh > 0.1) box(group, 0.08, hh, 0.08, x, hh / 2, r.z1 - f * run, M.steelDark);
            }
            const f0 = sx === -1 ? 0.4 : 0;
            const fc = (f0 + 1) / 2;
            const rail = box(group, 0.05, 0.05, len * (1 - f0), x, fc * rise + 0.92, r.z1 - fc * run, goldMat);
            rail.rotation.x = theta;
            for (let i = 1; i < n; i += 2) {
                if ((i + 1) / n < f0) continue;
                const h = ((i + 1) * rise) / n;
                box(group, 0.022, 0.9, 0.022, x, h + 0.45, r.z1 - (i + 0.5) * stepD, goldMat);
            }
        }
        for (const f of [0.5, 0.78]) {
            const hh = f * rise - 0.24;
            box(group, w, 0.07, 0.07, r.cx, hh, r.z1 - f * run, M.steelDark);
            if (f === 0.78) cyl(group, 0.05, 0.05, hh, r.cx, hh / 2, r.z1 - f * run, M.steelDark, 10);
        }
        box(group, w + 0.1, 0.1, 0.5, r.cx, rise - 0.08, r.z0 + 0.1, M.steelDark);
    }

    function waiterTrips(floor, r, door, pass) {
        const tables = floor.tables || [];
        if (!tables.length || door.nz !== 1) return null;
        const W = r.W, D = r.D;
        const P = (px, py) => [px / PX - W / 2, py / PX - D / 2];
        const zones = floor.zones || [];
        const patio = zones.find((q) => q.type === 'patio');
        const patioDoor = zones.find((q) => q.type === 'door' && /patio/i.test(q.label || ''));
        const inside = [door.cx, door.cz - 0.8];
        const hallZ = door.cz + 0.85;
        const aisleX = Math.min(...tables.map((t) => t.x)) / PX - W / 2 - 0.75;
        const midZ = P(0, 320)[1];
        const inPatio = (t) => patio && t.x >= patio.x && t.x + t.w <= patio.x + patio.w && t.y >= patio.y && t.y + t.h <= patio.y + patio.h;

        return (t) => {
            const tx = (t.x + t.w / 2) / PX - W / 2;
            let path, stop, face;
            if (inPatio(t) && patioDoor) {
                const pdx = P(patioDoor.x + patioDoor.w / 2, 0)[0], pdz = P(0, patioDoor.y + patioDoor.h / 2)[1];
                const stopZ = P(0, patio.y + patio.h)[1] - 0.35;
                stop = [tx, stopZ]; face = Math.PI;
                path = [[pdx, hallZ], [pdx, pdz + 0.1], [pdx, stopZ]];
            } else if ((t.y + t.h / 2) / PX - D / 2 < midZ) {
                stop = [tx, t.y / PX - D / 2 - 0.6]; face = 0;
                path = [[tx, hallZ]];
            } else {
                stop = [tx, (t.y + t.h) / PX - D / 2 + 0.6]; face = Math.PI;
                path = [[aisleX, hallZ], [aisleX, stop[1]]];
            }
            const steps = [['go', pass[0], pass[1]], ['do', 'pickup', 1.6, 0], ['tray', true], ['go', inside[0], inside[1]], ['go', door.cx, hallZ]];
            for (const q of path) steps.push(['go', q[0], q[1]]);
            steps.push(['go', stop[0], stop[1]], ['do', 'serve', 2.4, face], ['tray', false]);
            for (const q of [...path].reverse()) steps.push(['go', q[0], q[1]]);
            steps.push(['go', door.cx, hallZ], ['go', inside[0], inside[1]], ['go', pass[0], pass[1]]);
            return steps;
        };
    }

    function buildZone(group, floor, z) {
        const r = rectOf(floor, z);
        switch (z.type) {
            case 'kitchen': {
                patch(group, r, ZONE_FLOOR.kitchen);
                const door = solidRoom(group, floor, z, r, { color: 0xd9dcde, leaf: 0x8a9096, windows: { top: 'ribbon', left: 'ribbon', right: 'ribbon' }, sign: 'Kitchen' });
                const zb = r.z0 + 0.35;
                box(group, r.w - 0.5, 0.9, 0.6, r.cx, 0.45, zb, steelMat);
                box(group, r.w - 0.5, 0.05, 0.64, r.cx, 0.92, zb, M.steelDark);

                const sx = r.x0 + 2.4;
                box(group, 1.2, 0.04, 0.62, sx, 0.96, zb, M.black);
                for (const [dx, dz] of [[-0.3, -0.12], [0.3, -0.12], [-0.3, 0.14], [0.3, 0.14]]) cyl(group, 0.11, 0.11, 0.02, sx + dx, 0.985, zb + dz, M.fire, 14);
                cyl(group, 0.16, 0.16, 0.2, sx - 0.3, 1.1, zb - 0.12, M.chrome);
                cyl(group, 0.165, 0.165, 0.025, sx - 0.3, 1.21, zb - 0.12, M.chrome);
                cyl(group, 0.2, 0.19, 0.05, sx + 0.3, 1.02, zb + 0.14, M.black);
                box(group, 0.3, 0.025, 0.04, sx + 0.58, 1.03, zb + 0.14, M.black);
                box(group, 1.5, 0.3, 0.75, sx, 2.0, zb + 0.05, M.steelDark);
                box(group, 0.5, 0.45, 0.45, sx, 2.35, zb - 0.05, M.steelDark);

                const px = r.x0 + 3.9;
                box(group, 0.6, 0.03, 0.4, px, 0.96, zb, M.wood2);
                box(group, 0.22, 0.012, 0.04, px + 0.1, 0.985, zb + 0.08, M.chrome);
                cyl(group, 0.12, 0.09, 0.09, px - 0.6, 1.0, zb, M.white);
                const kx2 = r.x0 + 5.4;
                box(group, 0.75, 0.03, 0.44, kx2, 0.955, zb, M.black);
                cyl(group, 0.02, 0.02, 0.3, kx2, 1.1, zb - 0.2, M.chrome, 8);
                box(group, 0.04, 0.04, 0.2, kx2, 1.24, zb - 0.1, M.chrome);

                const ix = r.cx - 1.2, iz = r.z0 + 2.15;
                box(group, 3.0, 0.88, 0.7, ix, 0.44, iz, steelMat);
                box(group, 3.04, 0.05, 0.74, ix, 0.9, iz, M.steelDark);
                for (let i = 0; i < 3; i++) cyl(group, 0.14, 0.14, 0.14 + i * 0.03, ix - 1.0 + i * 0.35, 0.99, iz, M.white);
                for (let i = 0; i < 2; i++) cyl(group, 0.1, 0.07, 0.08, ix + 0.5 + i * 0.3, 0.97, iz, M.porcelain);

                box(group, 0.9, 1.95, 0.7, r.x1 - 0.7, 0.98, r.z0 + 0.5, M.steelDark);
                box(group, 0.02, 1.4, 0.02, r.x1 - 0.3, 1.2, r.z0 + 0.88, M.chrome);
                box(group, 0.9, 0.02, 0.02, r.x1 - 0.7, 1.1, r.z0 + 0.86, steelMat);

                const passX = r.x1 - 2.7;
                box(group, 1.3, 0.9, 0.5, passX, 0.45, r.z1 - 0.4, steelMat);
                box(group, 1.34, 0.04, 0.54, passX, 0.92, r.z1 - 0.4, M.steelDark);
                for (let i = 0; i < 3; i++) { cyl(group, 0.13, 0.13, 0.025, passX - 0.4 + i * 0.4, 0.96, r.z1 - 0.4, M.white); cyl(group, 0.05, 0.05, 0.06, passX - 0.4 + i * 0.4, 1.5, r.z1 - 0.4, M.fire, 10); }
                box(group, 1.2, 0.03, 0.03, passX, 1.56, r.z1 - 0.4, M.chrome);

                const kds = makeKitchenMonitor();
                box(group, 1.58, 0.92, 0.05, r.x0 + 4.0, 1.62, r.z1 - 0.1, M.black);
                screenPlane(group, 1.5, 0.84, kds, r.x0 + 4.0, 1.62, r.z1 - 0.13, Math.PI);

                zoneLabel(group, r, z, 2.0);

                const lane = r.z0 + 1.3;
                addActor(group, 'chef', [sx, lane], [
                    ['go', sx, lane], ['do', 'stir', 8, Math.PI],
                    ['go', px, lane], ['do', 'chop', 6, Math.PI],
                    ['go', kx2, lane], ['do', 'wash', 3, Math.PI],
                    ['go', ix, lane], ['do', 'plate', 5, 0],
                    ['go', r.x1 - 0.7, lane], ['do', 'fridge', 2.5, Math.PI],
                ], { speed: 0.95 });

                if (door) {
                    const passStand = [passX, r.z1 - 1.05];
                    serve.trip = waiterTrips(floor, r, door, passStand);
                    if (serve.trip) {
                        addActor(group, 'waiter', passStand, [['do', 'idle', 0.5, 0]], {
                            speed: 1.25,
                            next: () => {
                                const job = serve.pending.shift();
                                const table = job && floor.tables.find((t) => t.id === job.tableId);
                                const steps = table ? serve.trip(table) : null;
                                if (steps && job.onServed) {
                                    const at = steps.findIndex((st) => st[0] === 'do' && st[1] === 'serve');
                                    steps.splice(at + 1, 0, ['call', job.onServed]);
                                }
                                return steps;
                            },
                        });
                    }
                }
                break;
            }
            case 'bathroom': {
                patch(group, r, 0xc5d2d8);
                solidRoom(group, floor, z, r, { color: 0xc9d6dc, leaf: 0x4f7f95, sign: 'Bathroom' });
                for (let i = 0; i < 2; i++) {
                    const tx = r.x0 + 0.62 + i * 1.0, tz = r.z0 + 0.32;
                    box(group, 0.42, 0.42, 0.2, tx, 0.62, tz - 0.05, M.porcelain);
                    const bowl = cyl(group, 0.19, 0.14, 0.4, tx, 0.22, tz + 0.2, M.porcelain);
                    bowl.scale.z = 1.35;
                    const seat = new THREE.Mesh(new THREE.TorusGeometry(0.15, 0.025, 8, 20), M.white);
                    seat.rotation.x = Math.PI / 2;
                    seat.position.set(tx, 0.43, tz + 0.2);
                    seat.scale.y = 1.35;
                    group.add(seat);
                    cyl(group, 0.03, 0.03, 0.03, tx, 0.86, tz - 0.05, M.chrome, 8);
                    cyl(group, 0.07, 0.07, 0.12, tx + 0.28, 0.35, tz, M.white, 10);
                }
                for (const dx of [0.1, 1.1, 2.1]) box(group, 0.04, 1.75, 1.15, r.x0 + dx, 1.0, r.z0 + 0.65, new THREE.MeshStandardMaterial({ color: 0xaec3cc, roughness: 0.5 }));
                for (let i = 0; i < 2; i++) {
                    const vz = r.z0 + 1.55 + i * 0.85;
                    box(group, 0.55, 0.85, 0.7, r.x0 + 0.32, 0.43, vz, M.cabinet);
                    box(group, 0.58, 0.05, 0.74, r.x0 + 0.32, 0.88, vz, M.porcelain);
                    box(group, 0.36, 0.03, 0.4, r.x0 + 0.34, 0.9, vz, std(0xb9c6cc, 0.2));
                    cyl(group, 0.02, 0.02, 0.2, r.x0 + 0.12, 1.0, vz, M.chrome, 8);
                    box(group, 0.14, 0.03, 0.03, r.x0 + 0.2, 1.1, vz, M.chrome);
                    box(group, 0.04, 0.9, 0.68, r.x0 + 0.06, 1.65, vz, M.mirror);
                    box(group, 0.045, 0.94, 0.72, r.x0 + 0.055, 1.65, vz, goldMat);
                }
                box(group, 0.3, 0.22, 0.14, r.x1 - 0.12, 1.35, r.z0 + 2.9, M.steelDark);
                cyl(group, 0.14, 0.11, 0.4, r.x1 - 0.3, 0.2, r.z1 - 0.3, M.steelDark);
                cyl(group, 0.035, 0.035, 0.12, r.x0 + 0.3, 1.0, r.z0 + 1.2, M.amberBottle, 8);
                zoneLabel(group, r, z, 2.0);
                break;
            }
            case 'office': {
                patch(group, r, 0x33302c);
                solidRoom(group, floor, z, r, { color: 0xcfc3b4, leaf: 0x5a3d2b, windows: { left: 'tall', bottom: 'tall' }, sign: 'Office' });
                box(group, 2.0, 0.02, 2.0, r.x0 + 1.35, 0.05, r.z0 + 1.15, M.rug);
                const dx0 = r.x0 + 1.55, dz0 = r.z0 + 3.7;
                box(group, 1.6, 0.05, 0.78, dx0, 0.75, dz0, woodMat);
                for (const sx of [-1, 1]) box(group, 0.05, 0.72, 0.7, dx0 + sx * 0.74, 0.38, dz0, darkMat);
                box(group, 0.5, 0.5, 0.65, dx0 + 0.45, 0.28, dz0 + 0.02, darkMat);
                box(group, 0.6, 0.38, 0.03, dx0 - 0.1, 1.15, dz0 - 0.2, M.black);
                box(group, 0.06, 0.16, 0.06, dx0 - 0.1, 0.86, dz0 - 0.2, M.black);
                const scr = canvasTexture(256, 160);
                scr.ctx.fillStyle = '#f4f7fb'; scr.ctx.fillRect(0, 0, 256, 160);
                scr.ctx.fillStyle = '#2f6b3f'; scr.ctx.fillRect(0, 0, 256, 18);
                scr.ctx.strokeStyle = '#c3cbd5'; scr.ctx.fillStyle = '#6b7684'; scr.ctx.font = '9px Arial';
                for (let i = 0; i < 9; i++) { scr.ctx.beginPath(); scr.ctx.moveTo(0, 28 + i * 14); scr.ctx.lineTo(256, 28 + i * 14); scr.ctx.stroke(); scr.ctx.fillText(`Order ${2040 + i}   ${(18 + i * 7.35).toFixed(2)}   paid`, 8, 24 + i * 14); }
                scr.tex.needsUpdate = true;
                screenPlane(group, 0.54, 0.32, scr.tex, dx0 - 0.1, 1.15, dz0 - 0.178, 0);
                box(group, 0.42, 0.015, 0.14, dx0 - 0.1, 0.78, dz0 + 0.12, M.black);
                box(group, 0.07, 0.02, 0.1, dx0 + 0.2, 0.78, dz0 + 0.12, M.black);
                box(group, 0.28, 0.03, 0.36, dx0 + 0.5, 0.79, dz0 + 0.15, M.paper);
                box(group, 0.26, 0.015, 0.34, dx0 + 0.52, 0.82, dz0 + 0.14, M.paper);
                box(group, 0.3, 0.012, 0.38, dx0 - 0.62, 0.78, dz0 + 0.1, std(0xc9a45c, 0.8));
                cyl(group, 0.04, 0.04, 0.1, dx0 - 0.68, 0.83, dz0 - 0.2, M.black, 10);
                officeChair(group, dx0 - 0.1, dz0 + 0.95, Math.PI);
                for (let i = 0; i < 2; i++) {
                    box(group, 0.55, 1.2, 0.6, r.x1 - 0.4, 0.6, r.z0 + 0.35 + i * 0.0, M.steelDark);
                }
                box(group, 0.5, 0.012, 0.01, r.x1 - 0.4, 0.4, r.z0 + 0.66, M.chrome);
                box(group, 0.5, 0.012, 0.01, r.x1 - 0.4, 0.85, r.z0 + 0.66, M.chrome);
                box(group, 0.4, 0.2, 0.34, r.x1 - 0.4, 1.3, r.z0 + 0.35, M.white);
                sofa(group, r.x0 + 1.35, r.z0 + 0.55, 1.9, 0, M.sofa);
                box(group, 0.9, 0.4, 0.6, r.x0 + 1.35, 0.22, r.z0 + 1.65, woodMat);
                box(group, 0.3, 0.015, 0.22, r.x0 + 1.25, 0.43, r.z0 + 1.65, M.paper);
                cyl(group, 0.04, 0.04, 0.1, r.x0 + 1.6, 0.48, r.z0 + 1.65, M.white, 10);
                plant(group, r.x1 - 0.35, r.z1 - 0.4, 1.2);
                cyl(group, 0.22, 0.22, 0.03, r.x1 - 0.03, 2.1, r.z0 + 2.3, M.white);
                const clk = group.children[group.children.length - 1]; clk.rotation.z = Math.PI / 2;
                box(group, 0.05, 0.4, 0.6, r.x1 - 0.04, 1.65, r.z0 + 4.3, goldMat);
                box(group, 0.03, 0.34, 0.54, r.x1 - 0.06, 1.65, r.z0 + 4.3, M.felt || M.black);
                zoneLabel(group, r, z, 2.0);
                addActor(group, 'clerk', [dx0 - 0.1, dz0 + 0.8], [
                    ['do', 'type', 9, Math.PI], ['do', 'paper', 6, Math.PI], ['do', 'type', 7, Math.PI], ['do', 'paper', 5, Math.PI],
                ], { seat: true, face: Math.PI });
                break;
            }
            case 'vip': {
                const carpet = patch(group, r, ZONE_FLOOR.vip);
                const edge = new THREE.LineSegments(new THREE.EdgesGeometry(carpet.geometry), new THREE.LineBasicMaterial({ color: 0xfcd34d }));
                edge.position.copy(carpet.position);
                group.add(edge);
                solidRoom(group, floor, z, r, { color: 0x8d9298, leaf: 0x5a1624, windows: { top: 'tallBlack', left: 'tallBlack' }, sign: '★ VIP' });
                sofa(group, r.x1 - 0.6, r.z0 + 2.4, 1.9, -Math.PI / 2, M.sofa);
                box(group, 0.45, 0.5, 0.45, r.x1 - 1.3, 0.26, r.z0 + 3.3, goldMat);
                cyl(group, 0.02, 0.02, 1.5, r.x1 - 0.4, 0.78, r.z0 + 3.6, goldMat, 8);
                cyl(group, 0.18, 0.12, 0.25, r.x1 - 0.4, 1.6, r.z0 + 3.6, std(0xf3e2b3, 0.9), 14);
                plant(group, r.x1 - 0.4, r.z0 + 0.4, 1.3);
                zoneLabel(group, r, { ...z, label: `★ ${z.label}`, note: 'Private lounge' }, 2.1);
                break;
            }
            case 'patio': {
                patch(group, r, ZONE_FLOOR.patio);
                buildRailing(group, r.cx, r.cz, r.w, r.d);
                for (const fx of [-0.42, -0.1, 0.22, 0.44]) { box(group, 0.7, 0.3, 0.3, r.cx + r.w * fx, 0.15, r.z0 + 0.25, woodMat); plant(group, r.cx + r.w * fx, r.z0 + 0.25, 0.8); }
                zoneLabel(group, r, z, 1.7);
                break;
            }
            case 'bar': {
                patch(group, r, 0x2b2118);
                const st = (floor.zones || []).find((q) => q.type === 'stairs' && (q.dir || 'up') === 'up');
                const sr = st ? rectOf(floor, st) : null;
                const rise = LEVEL_H;
                const clear = (zz) => (sr ? Math.max(0, (rise * (sr.z1 - zz)) / sr.d) : 3);
                const wallX = r.x1;
                const cxb = wallX - 1.85;
                const ax = wallX - 1.0;
                const zA = r.z0 + 0.45, zB = r.z1 - 0.2, len = zB - zA, zm = (zA + zB) / 2;

                box(group, 0.72, 1.02, len, cxb, 0.51, zm, woodMat);
                box(group, 0.92, 0.06, len + 0.06, cxb - 0.03, 1.05, zm, goldMat);
                box(group, 0.05, 0.06, len, cxb - 0.55, 0.2, zm, goldMat);
                for (let i = 0; i < 5; i++) {
                    const sz = zA + 0.3 + (i * (len - 0.6)) / 4;
                    cyl(group, 0.18, 0.18, 0.06, cxb - 0.9, 0.72, sz, goldMat, 14);
                    cyl(group, 0.025, 0.025, 0.7, cxb - 0.9, 0.36, sz, darkMat, 8);
                    cyl(group, 0.14, 0.14, 0.02, cxb - 0.9, 0.03, sz, darkMat, 12);
                }
                for (let i = 0; i < 3; i++) {
                    cyl(group, 0.025, 0.025, 0.26, cxb + 0.1, 1.21, zm - 0.5 + i * 0.35, M.chrome, 8);
                    box(group, 0.05, 0.05, 0.05, cxb + 0.1, 1.38, zm - 0.5 + i * 0.35, i === 1 ? goldMat : M.black);
                }
                for (let i = 0; i < 4; i++) cyl(group, 0.04, 0.032, 0.11, cxb - 0.2, 1.14, zA + 0.5 + i * 0.28, M.glass, 10);
                cyl(group, 0.04, 0.05, 0.2, cxb - 0.1, 1.18, zB - 0.5, M.chrome, 10);
                box(group, 0.3, 0.2, 0.26, cxb + 0.1, 1.18, zB - 0.9, M.black);
                for (let i = 0; i < 3; i++) {
                    const pz = zA + 0.6 + i * ((len - 1.2) / 2);
                    cyl(group, 0.005, 0.005, 1.2, cxb - 0.1, 2.0, pz, lampMat, 4);
                    cyl(group, 0.04, 0.17, 0.2, cxb - 0.1, 1.4, pz, lampMat, 14);
                }

                const ux = wallX - 0.4;
                const zEndBack = sr ? Math.min(sr.z0 + 1.5, sr.z1 - 0.1) : zB;
                for (let zz = zA; zz < zEndBack - 0.05; zz += 0.55) {
                    const zc2 = Math.min(zz + 0.275, zEndBack);
                    const lim = sr && zc2 > sr.z0 ? clear(zc2 + 0.27) - 0.08 : 0.9;
                    if (lim < 0.5) break;
                    const unitH = Math.min(0.88, lim);
                    const fridge = Math.round((zz - zA) / 0.55) % 2 === 0;
                    box(group, 0.6, unitH, 0.53, ux, unitH / 2, zc2, fridge ? M.steelDark : M.cabinet);
                    if (unitH >= 0.88) box(group, 0.64, 0.04, 0.57, ux, unitH + 0.02, zc2, goldMat);
                    box(group, 0.02, Math.min(0.3, unitH * 0.5), 0.02, ux - 0.31, unitH * 0.55, zc2, fridge ? M.chrome : goldMat);
                    if (fridge) box(group, 0.01, unitH - 0.12, 0.45, ux - 0.305, unitH / 2, zc2, M.glass);
                }
                const mats = [M.redBottle, M.greenBottle, M.amberBottle, M.clearBottle];
                for (const tier of [1.3, 1.7]) {
                    let zEnd = zEndBack;
                    if (sr) zEnd = Math.min(zEnd, sr.z1 - ((tier + 0.25) / rise) * sr.d);
                    if (zEnd - zA < 0.6) continue;
                    box(group, 0.3, 0.035, zEnd - zA, wallX - 0.2, tier, (zA + zEnd) / 2, woodMat);
                    for (let k = 0, nb = Math.floor((zEnd - zA) / 0.2); k < nb; k++) bottle(group, wallX - 0.2 + ((k % 2) - 0.5) * 0.06, tier + 0.018, zA + 0.12 + k * 0.2, mats[(k + Math.round(tier * 3)) % 4], 0.2 + (k % 3) * 0.04);
                }

                zoneLabel(group, r, z, 2.35);
                addActor(group, 'bartender', [ax, zm], [
                    ['go', ax, zA + 0.8], ['do', 'reach', 3, Math.PI / 2],
                    ['go', ax, zm - 0.3], ['do', 'shake', 5, -Math.PI / 2], ['do', 'pour', 4, -Math.PI / 2],
                    ['go', ax, zB - 0.7], ['do', 'polish', 5, -Math.PI / 2],
                    ['go', ax, zm + 0.3], ['do', 'shake', 4, -Math.PI / 2], ['do', 'pour', 3.5, -Math.PI / 2],
                ], { speed: 0.9 });
                break;
            }
            case 'stairs': {
                const up = (z.dir || 'up') === 'up';
                if (up) {
                    openStairs(group, r);
                } else {
                    const wz1 = r.z1 - 0.36 * r.d, hl = wz1 - r.z0;
                    for (const sx of [-1, 1]) box(group, 0.05, 0.9, hl, r.cx + sx * r.w * 0.46, 0.5, r.z0 + hl / 2, goldMat);
                    box(group, r.w * 0.92, 0.9, 0.05, r.cx, 0.5, wz1, goldMat);
                }
                zoneLabel(group, r, { ...z, note: z.note || (up ? 'Up' : 'Down') }, up ? 1.0 : 1.5);
                break;
            }
            case 'reception': {
                box(group, r.w * 0.85, 1.05, 0.75, r.cx, 0.52, r.cz + 0.1, woodMat);
                box(group, r.w * 0.85 + 0.06, 0.05, 0.82, r.cx, 1.07, r.cz + 0.1, goldMat);
                box(group, 0.45, 0.32, 0.05, r.cx + r.w * 0.25, 1.28, r.cz + 0.05, darkMat);
                cyl(group, 0.06, 0.06, 0.03, r.cx - r.w * 0.2, 1.11, r.cz + 0.25, goldMat);
                box(group, 0.3, 0.02, 0.2, r.cx - r.w * 0.05, 1.1, r.cz + 0.3, M.paper);
                plant(group, r.x0 + 0.2, r.z1 - 0.2, 1.1);
                plant(group, r.x1 - 0.2, r.z1 - 0.2, 1.1);
                zoneLabel(group, r, z, 1.95);
                break;
            }
            case 'waiting': {
                if (r.d > r.w) {
                    for (let i = 0; i < 3; i++) sofa(group, r.x1 - 0.55, r.z0 + 0.95 + i * 1.25, 0.85, -Math.PI / 2, M.sofa);
                    box(group, 0.55, 0.38, 0.55, r.x1 - 1.4, 0.21, r.cz, woodMat);
                    box(group, 0.22, 0.015, 0.3, r.x1 - 1.4, 0.41, r.cz, M.paper);
                    cyl(group, 0.02, 0.02, 1.4, r.x1 - 0.35, 0.72, r.z0 + 0.3, goldMat, 8);
                    cyl(group, 0.16, 0.11, 0.22, r.x1 - 0.35, 1.5, r.z0 + 0.3, std(0xf3e2b3, 0.9), 14);
                    plant(group, r.x1 - 0.4, r.z1 - 0.4, 1.2);
                } else {
                    box(group, r.w * 0.92, 0.42, 0.45, r.cx, 0.22, r.cz + 0.1, new THREE.MeshStandardMaterial({ color: 0x6b2a36, roughness: 0.7 }));
                    box(group, r.w * 0.92, 0.4, 0.1, r.cx, 0.6, r.cz + 0.32, new THREE.MeshStandardMaterial({ color: 0x6b2a36, roughness: 0.7 }));
                }
                zoneLabel(group, r, z, 1.5);
                break;
            }
            case 'door': {
                const horizontal = r.w >= r.d;
                const door = new THREE.Mesh(
                    new THREE.BoxGeometry(horizontal ? r.w : 0.06, 2.3, horizontal ? 0.06 : r.d),
                    new THREE.MeshStandardMaterial({ color: GOLD, metalness: 0.7, roughness: 0.4, transparent: true, opacity: 0.3 }),
                );
                door.position.set(r.cx, 1.15, r.cz);
                group.add(door);
                const frame = new THREE.LineSegments(new THREE.EdgesGeometry(door.geometry), new THREE.LineBasicMaterial({ color: 0xfcd34d }));
                frame.position.copy(door.position);
                group.add(frame);
                if (z.label === 'Entrance') {
                    plant(group, r.x0 - 0.6, r.cz - 0.5, 1.3);
                    plant(group, r.x1 + 0.6, r.cz - 0.5, 1.3);
                    const s = makeLabel('ENTRANCE', '', true);
                    s.scale.set(3.0, 0.6, 1);
                    s.position.set(r.cx, 2.65, r.cz + 0.25);
                    group.add(s);
                }
                break;
            }
            default:
                break;
        }
    }

    function buildRailing(group, ox, oz, W, D) {
        const h = 1.0;
        const pts = [];
        const corners = [[ox - W / 2, oz - D / 2], [ox + W / 2, oz - D / 2], [ox + W / 2, oz + D / 2], [ox - W / 2, oz + D / 2]];
        for (let i = 0; i < 4; i++) {
            const [x1, z1] = corners[i];
            const [x2, z2] = corners[(i + 1) % 4];
            pts.push(x1, h, z1, x2, h, z2, x1, h * 0.5, z1, x2, h * 0.5, z2);
        }
        const g = new THREE.BufferGeometry();
        g.setAttribute('position', new THREE.Float32BufferAttribute(pts, 3));
        group.add(new THREE.LineSegments(g, lineMat));
        const posts = 6;
        for (let i = 0; i <= posts; i++) {
            for (const z of [oz - D / 2, oz + D / 2]) {
                const p = new THREE.Mesh(new THREE.CylinderGeometry(0.03, 0.03, h, 8), goldMat);
                p.position.set(ox - W / 2 + (W * i) / posts, h / 2, z);
                group.add(p);
            }
        }
        for (const x of [ox - W / 2, ox + W / 2]) {
            for (let i = 1; i < 4; i++) {
                const p = new THREE.Mesh(new THREE.CylinderGeometry(0.03, 0.03, h, 8), goldMat);
                p.position.set(x, h / 2, oz - D / 2 + (D * i) / 4);
                group.add(p);
            }
        }
        for (let i = 0; i < 14; i++) {
            const b = new THREE.Mesh(new THREE.SphereGeometry(0.06, 8, 8), lampMat);
            b.position.set(ox - W / 2 + (W * (i + 0.5)) / 14, h + 0.25 + Math.sin(i * 1.3) * 0.06, oz + D / 2);
            group.add(b);
        }
    }

    function buildPlants(group, W, D) {
        for (const [x, z] of [[-W / 2 + 0.5, -D / 2 + 0.5], [W / 2 - 0.5, -D / 2 + 0.5], [W / 2 - 0.5, D / 2 - 0.5], [-W / 2 + 0.5, D / 2 - 0.5]]) {
            const pot = new THREE.Mesh(new THREE.CylinderGeometry(0.22, 0.17, 0.4, 14), potMat);
            pot.position.set(x, 0.24, z);
            const bush = new THREE.Mesh(new THREE.IcosahedronGeometry(0.34, 1), leafMat);
            bush.position.set(x, 0.72, z);
            group.add(pot, bush);
        }
    }

    function buildTable(group, floor, t) {
        const W = floor.width / PX, D = floor.height / PX;
        const tw = t.w / PX, td = t.h / PX;
        const cx = (t.x + t.w / 2) / PX - W / 2;
        const cz = (t.y + t.h / 2) / PX - D / 2;
        const g = new THREE.Group();
        g.position.set(cx, 0, cz);
        group.add(g);

        const topMat = new THREE.MeshStandardMaterial({ color: STATES.idle.color, roughness: 0.45, metalness: 0.1 });
        const round = t.shape === 'round';
        const top = new THREE.Mesh(
            round ? new THREE.CylinderGeometry(Math.min(tw, td) / 2, Math.min(tw, td) / 2, 0.07, 40) : new THREE.BoxGeometry(tw, 0.07, td),
            topMat,
        );
        top.position.y = 0.78;
        g.add(top);

        if (round) {
            const base = new THREE.Mesh(new THREE.CylinderGeometry(0.09, 0.14, 0.76, 14), darkMat);
            base.position.y = 0.38;
            g.add(base);
        } else {
            for (const sx of [-1, 1]) for (const sz of [-1, 1]) {
                const leg = new THREE.Mesh(new THREE.BoxGeometry(0.07, 0.76, 0.07), darkMat);
                leg.position.set(sx * (tw / 2 - 0.1), 0.38, sz * (td / 2 - 0.1));
                g.add(leg);
            }
        }

        const cord = new THREE.Mesh(new THREE.CylinderGeometry(0.008, 0.008, 1.1, 4), darkMat);
        cord.position.y = WALL_H - 0.55;
        const bulb = new THREE.Mesh(new THREE.SphereGeometry(0.1, 12, 12), lampMat);
        bulb.position.y = 1.62;
        g.add(cord, bulb);

        if (t.is_vip) {
            const rugGeo = round
                ? new THREE.CylinderGeometry(Math.min(tw, td) / 2 + 0.85, Math.min(tw, td) / 2 + 0.85, 0.03, 48)
                : new THREE.BoxGeometry(tw + 1.7, 0.03, td + 1.7);
            const rug = new THREE.Mesh(rugGeo, new THREE.MeshStandardMaterial({ color: 0x5a1624, roughness: 0.95 }));
            rug.position.y = 0.055;
            const rugEdge = new THREE.LineSegments(new THREE.EdgesGeometry(rugGeo), new THREE.LineBasicMaterial({ color: 0xfcd34d }));
            rugEdge.position.copy(rug.position);
            g.add(rug, rugEdge);
        }

        const chairs = [];
        const chairMat = new THREE.MeshStandardMaterial({ color: STATES.idle.color, roughness: 0.6, metalness: 0.05 });
        const placeChair = (x, z, faceAngle) => {
            const c = new THREE.Group();
            const seat = new THREE.Mesh(new THREE.BoxGeometry(0.34, 0.06, 0.34), chairMat);
            seat.position.y = 0.44;
            const back = new THREE.Mesh(new THREE.BoxGeometry(0.34, 0.34, 0.05), chairMat);
            back.position.set(0, 0.64, -0.15);
            const stem = new THREE.Mesh(new THREE.CylinderGeometry(0.03, 0.04, 0.42, 8), darkMat);
            stem.position.y = 0.21;
            c.add(seat, back, stem);
            c.position.set(x, 0, z);
            c.rotation.y = faceAngle;
            g.add(c);
            chairs.push(seat, back);
        };
        const n = Math.max(1, t.seats);
        if (round) {
            const r = Math.min(tw, td) / 2 + 0.3;
            for (let i = 0; i < n; i++) {
                const a = (i / n) * Math.PI * 2;
                placeChair(Math.sin(a) * r, Math.cos(a) * r, a + Math.PI);
            }
        } else {
            const alongX = tw >= td;
            const sidesA = Math.ceil(n / 2), sidesB = Math.floor(n / 2);
            const span = alongX ? tw : td;
            const off = (alongX ? td : tw) / 2 + 0.3;
            const spread = (count, i) => (count === 1 ? 0 : (-span / 2 + 0.35) + ((span - 0.7) * i) / (count - 1));
            for (let i = 0; i < sidesA; i++) {
                const p = spread(sidesA, i);
                alongX ? placeChair(p, off, Math.PI) : placeChair(off, p, -Math.PI / 2);
            }
            for (let i = 0; i < sidesB; i++) {
                const p = spread(sidesB, i);
                alongX ? placeChair(p, -off, 0) : placeChair(-off, p, Math.PI / 2);
            }
        }

        const ringR = Math.max(tw, td) / 2 + (t.is_vip ? 1.1 : 0.75);
        const ring = new THREE.Mesh(
            new THREE.RingGeometry(ringR - 0.07, ringR, 48),
            new THREE.MeshBasicMaterial({ color: 0xfcd34d, transparent: true, opacity: 0.9, side: THREE.DoubleSide, depthWrite: false }),
        );
        ring.rotation.x = -Math.PI / 2;
        ring.position.y = 0.06;
        ring.visible = false;
        g.add(ring);

        const label = makeLabel(t.is_vip ? `★ ${t.label}` : t.label, t.is_vip ? `VIP · ${t.seats} seats` : `${t.seats} seats`, false, !!t.is_vip);
        label.position.y = 1.3;
        g.add(label);

        const entry = { id: t.id, floorId: floor.id, top, topMat, chairMat, chairs, ring, group: g, label, state: 'idle', data: t };
        tables.set(t.id, entry);
        for (const m of [top, ...chairs]) {
            m.userData.tableId = t.id;
            pickables.push(m);
        }
    }

    let selectedId = null;
    let hasTime = false;

    function paint(entry, stateName) {
        const s = STATES[stateName] || STATES.idle;
        entry.state = stateName;
        entry.topMat.color.setHex(s.color);
        entry.topMat.emissive.setHex(s.emissive);
        entry.topMat.emissiveIntensity = s.ei;
        const dim = new THREE.Color(s.color).multiplyScalar(stateName === 'idle' ? 0.8 : 0.65);
        entry.chairMat.color.copy(dim);
        entry.chairMat.emissive.setHex(s.emissive);
        entry.chairMat.emissiveIntensity = s.ei * 0.3;
    }

    function stateFor(id, states) {
        return hasTime ? (states[id] || 'booked') : 'idle';
    }

    let lastStates = {};
    function applyAll() {
        for (const [id, e] of tables) {
            paint(e, id === selectedId ? 'selected' : stateFor(id, lastStates));
            e.ring.visible = id === selectedId;
            e.ring.material.color.setHex(id === selectedId ? 0xfcd34d : 0xffffff);
        }
    }

    const bb = levels.reduce((b, l) => ({
        minX: Math.min(b.minX, l.cx - l.W / 2), maxX: Math.max(b.maxX, l.cx + l.W / 2),
        minZ: Math.min(b.minZ, l.cz - l.D / 2), maxZ: Math.max(b.maxZ, l.cz + l.D / 2),
        maxY: Math.max(b.maxY, l.y + (l.outdoor ? 1 : WALL_H)),
    }), { minX: 0, maxX: 0, minZ: 0, maxZ: 0, maxY: 0 });
    const center = new THREE.Vector3((bb.minX + bb.maxX) / 2, 0, (bb.minZ + bb.maxZ) / 2);
    const extent = Math.max(bb.maxX - bb.minX, bb.maxZ - bb.minZ, 8);
    const totalH = bb.maxY;
    let tween = null;
    let focusId = null;
    let explodeT = 0;
    let explodeTarget = 0;

    const floaters = [];
    let progress = null;
    let progressAcc = 0;

    const PROGRESS_THEME = {
        queued: { label: 'RECEIVED', from: '#fbbf24', to: '#d97706' },
        cooking: { label: 'COOKING', from: '#4ade80', to: '#15803d' },
        ready: { label: 'READY', from: '#facc15', to: '#16a34a' },
        cancelled: { label: 'CANCELLED', from: '#f87171', to: '#b91c1c' },
    };

    function roundedRect(ctx, x, y, w, h, r) {
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.arcTo(x + w, y, x + w, y + h, r);
        ctx.arcTo(x + w, y + h, x, y + h, r);
        ctx.arcTo(x, y + h, x, y, r);
        ctx.arcTo(x, y, x + w, y, r);
        ctx.closePath();
    }

    function ensureProgress() {
        if (progress) return progress;
        const level = levels[0];
        const zone = (level?.floor.zones || []).find((q) => q.type === 'kitchen');
        if (!zone) return null;
        const r = rectOf(level.floor, zone);
        const canvas = document.createElement('canvas');
        canvas.width = 1024;
        canvas.height = 256;
        const tex = new THREE.CanvasTexture(canvas);
        tex.colorSpace = THREE.SRGBColorSpace;
        const sprite = new THREE.Sprite(new THREE.SpriteMaterial({ map: tex, transparent: true, depthTest: false }));
        sprite.scale.set(5.2, 1.3, 1);
        sprite.renderOrder = 30;
        sprite.position.set(r.cx, 3.6, r.cz);
        level.group.add(sprite);
        floaters.push({ sprite, base: 3.6 });
        progress = { canvas, ctx: canvas.getContext('2d'), tex, sprite, r, shown: 0, drawn: false, data: { phase: 'queued', percent: 0, time: '', title: '', note: '' } };
        return progress;
    }

    function drawProgress(t) {
        const p = progress;
        const { ctx, canvas, data } = p;
        const theme = PROGRESS_THEME[data.phase] || PROGRESS_THEME.queued;
        const w = canvas.width, h = canvas.height;
        ctx.clearRect(0, 0, w, h);
        roundedRect(ctx, 6, 6, w - 12, h - 12, 40);
        ctx.fillStyle = 'rgba(8, 10, 12, 0.9)';
        ctx.fill();
        ctx.lineWidth = 4;
        ctx.strokeStyle = theme.from;
        ctx.stroke();

        ctx.textBaseline = 'middle';
        ctx.textAlign = 'left';
        ctx.fillStyle = theme.from;
        ctx.font = '700 38px Arial';
        ctx.fillText(`${theme.label}  ·  ${data.title}`, 44, 62);
        ctx.textAlign = 'right';
        ctx.fillStyle = '#ffffff';
        ctx.font = '800 84px Arial';
        ctx.fillText(data.time, w - 44, 66);

        const bx = 44, by = 124, bw = w - 88, bh = 64;
        roundedRect(ctx, bx, by, bw, bh, 32);
        ctx.fillStyle = '#1f252c';
        ctx.fill();
        const fill = Math.max(0, Math.min(100, p.shown));
        const fw = data.phase === 'queued' ? bw : (bw * fill) / 100;
        if (fw > 2) {
            ctx.save();
            roundedRect(ctx, bx, by, bw, bh, 32);
            ctx.clip();
            const g = ctx.createLinearGradient(bx, 0, bx + bw, 0);
            g.addColorStop(0, theme.from);
            g.addColorStop(1, theme.to);
            ctx.fillStyle = g;
            ctx.globalAlpha = data.phase === 'queued' ? 0.35 : 1;
            ctx.fillRect(bx, by, fw, bh);
            if (data.phase !== 'ready') {
                ctx.globalAlpha = 0.22;
                ctx.fillStyle = '#ffffff';
                const off = (t * 60) % 48;
                for (let sx = bx - 48 + off; sx < bx + fw; sx += 48) {
                    ctx.beginPath();
                    ctx.moveTo(sx, by + bh);
                    ctx.lineTo(sx + 24, by + bh);
                    ctx.lineTo(sx + 48, by);
                    ctx.lineTo(sx + 24, by);
                    ctx.closePath();
                    ctx.fill();
                }
            }
            ctx.restore();
        }
        ctx.textAlign = 'center';
        ctx.fillStyle = '#ffffff';
        ctx.font = '800 40px Arial';
        ctx.fillText(data.phase === 'queued' ? 'Waiting for the kitchen' : `${Math.round(fill)}%`, w / 2, by + bh / 2 + 2);
        ctx.font = '600 28px Arial';
        ctx.fillStyle = '#9aa5b1';
        ctx.fillText(data.note || '', w / 2, 226);
        p.tex.needsUpdate = true;
    }

    function frame(focusFloorId, instant = false) {
        focusId = focusFloorId;
        let target, pos;
        const level = levels.find((l) => l.floor.id === focusFloorId);

        levels.forEach((l) => {
            l.group.visible = !level || l.outdoor || l.y <= level.y;
        });

        const k = 1 + explodeTarget;
        if (level) {
            target = new THREE.Vector3(level.cx, level.y * k + 0.8, level.cz);
            const d = Math.max(level.W, level.D) * 1.2 * (camera.aspect > 1.5 ? 0.88 : 1);
            pos = new THREE.Vector3(target.x + d * 0.3, target.y + d * 0.8, target.z + d * 0.9);
        } else {
            const H = totalH * k;
            target = new THREE.Vector3(center.x, Math.max(1.2, H * 0.35), center.z);
            const d = (extent * 1.32 + H * 0.5) * (camera.aspect > 1.5 ? 0.88 : 1);
            pos = new THREE.Vector3(target.x + d * 0.5, target.y + d * 0.6, target.z + d * 0.85);
        }
        moveCamera(pos, target, instant);
    }

    function moveCamera(pos, target, instant) {
        if (instant) {
            camera.position.copy(pos);
            controls.target.copy(target);
            controls.update();
            return;
        }
        tween = { t: 0, fromPos: camera.position.clone(), fromTarget: controls.target.clone(), toPos: pos, toTarget: target };
    }

    const ray = new THREE.Raycaster();
    const mouse = new THREE.Vector2();
    let downAt = null;
    let hoverId = null;

    function pickAt(ev) {
        const r = renderer.domElement.getBoundingClientRect();
        mouse.set(((ev.clientX - r.left) / r.width) * 2 - 1, -((ev.clientY - r.top) / r.height) * 2 + 1);
        ray.setFromCamera(mouse, camera);
        const visible = pickables.filter((m) => m.parent && isVisible(m));
        const hit = ray.intersectObjects(visible, false)[0];
        return hit ? hit.object.userData.tableId : null;
    }
    function isVisible(obj) {
        for (let o = obj; o; o = o.parent) if (!o.visible) return false;
        return true;
    }

    const el = renderer.domElement;
    const onDown = (e) => { downAt = { x: e.clientX, y: e.clientY }; controls.autoRotate = false; };
    const onUp = (e) => {
        if (downAt && Math.hypot(e.clientX - downAt.x, e.clientY - downAt.y) < 6) {
            const id = pickAt(e);
            if (id !== null) cb.onPick?.(id);
        }
        downAt = null;
    };
    const onMove = (e) => {
        if (e.buttons) return;
        const id = pickAt(e);
        if (id !== hoverId) {
            hoverId = id;
            el.style.cursor = id !== null ? 'pointer' : 'grab';
        }
        const r = container.getBoundingClientRect();
        cb.onHover?.(id === null ? null : { id, x: e.clientX - r.left, y: e.clientY - r.top });
    };
    const onLeave = () => { hoverId = null; cb.onHover?.(null); };
    el.addEventListener('pointerdown', onDown);
    el.addEventListener('pointerup', onUp);
    el.addEventListener('pointermove', onMove);
    el.addEventListener('pointerleave', onLeave);
    el.style.cursor = 'grab';

    function resize() {
        const w = container.clientWidth || 300, h = container.clientHeight || 300;
        renderer.setSize(w, h, false);
        camera.aspect = w / h;
        camera.updateProjectionMatrix();
    }
    const ro = new ResizeObserver(resize);
    ro.observe(container);
    resize();

    let running = true;
    let visibleOnScreen = true;
    const io = new IntersectionObserver((entries) => { visibleOnScreen = entries[0].isIntersecting; });
    io.observe(container);
    let last = performance.now();
    let elapsed = 0;
    let raf = 0;

    const ease = (t) => 1 - Math.pow(1 - t, 3);
    function loop() {
        raf = requestAnimationFrame(loop);
        const now = performance.now();
        if (!running || !visibleOnScreen || document.hidden) { last = now; return; }
        const dt = Math.min((now - last) / 1000, 0.05);
        last = now;
        elapsed += dt;
        const time = elapsed;
        updateActors(dt);
        for (const f of floaters) f.sprite.position.y = f.base + Math.sin(time * 1.8) * 0.08;
        if (progress) {
            progressAcc += dt;
            if (progressAcc > 0.07) {
                progressAcc = 0;
                progress.shown += (progress.data.percent - progress.shown) * 0.3;
                drawProgress(time);
            }
        }

        if (tween) {
            tween.t = Math.min(1, tween.t + dt / 0.95);
            const k = ease(tween.t);
            camera.position.lerpVectors(tween.fromPos, tween.toPos, k);
            controls.target.lerpVectors(tween.fromTarget, tween.toTarget, k);
            if (tween.t >= 1) tween = null;
        }

        if (selectedId !== null) {
            const e = tables.get(selectedId);
            if (e) {
                e.topMat.emissiveIntensity = 0.55 + 0.3 * Math.sin(time * 4);
                e.ring.material.opacity = 0.6 + 0.35 * Math.sin(time * 4);
            }
        }
        if (Math.abs(explodeTarget - explodeT) > 0.001) {
            explodeT += (explodeTarget - explodeT) * Math.min(1, dt * 4);
            for (const l of levels) if (!l.outdoor && l.y > 0) l.group.position.y = l.y * (1 + explodeT);
        }
        controls.update();
        renderer.render(scene, camera);
    }

    controls.addEventListener('start', () => { tween = null; });
    frame(null, true);
    applyAll();
    loop();

    return {
        setStates(states, timeChosen) {
            lastStates = states || {};
            hasTime = !!timeChosen;
            applyAll();
        },
        setSelected(id) {
            selectedId = id;
            applyAll();
            const e = id !== null ? tables.get(id) : null;
            if (e && !levels.find((l) => l.floor.id === e.floorId)?.group.visible) frame(e.floorId);
        },
        setProgress(data) {
            const p = ensureProgress();
            if (!p) return;
            p.data = { ...p.data, ...data };
            p.sprite.visible = data.phase !== 'done';
            if (!p.drawn) {
                p.shown = data.percent;
                p.drawn = true;
                drawProgress(0);
            }
        },
        focusKitchen(instant = false) {
            const p = ensureProgress();
            if (!p) return;
            const target = new THREE.Vector3(p.r.cx, 1.4, p.r.cz + 1.2);
            moveCamera(new THREE.Vector3(target.x + 2.5, 6.2, target.z + 10.5), target, instant);
        },
        actorsInfo() {
            return actors.map((a) => ({ kind: a.kind, x: Number(a.x.toFixed(2)), z: Number(a.z.toFixed(2)), action: a.action, step: a.i, steps: a.script.length, tray: a.tray }));
        },
        serveOrders(items) {
            for (const it of items) {
                if (serve.seen.has(it.key) || (it.age ?? 0) > 600) continue;
                serve.seen.add(it.key);
                serve.pending.push({ key: it.key, tableId: it.tableId, onServed: it.onServed });
            }
        },
        focusFloor(id) { frame(id); },
        resetView() { controls.autoRotate = false; frame(focusId); },
        topView() {
            const level = levels.find((l) => l.floor.id === focusId);
            const c = level ? new THREE.Vector3(level.cx, level.y * (1 + explodeTarget), level.cz) : center;
            const d = (level ? Math.max(level.W, level.D) : extent) * 1.35 + 3;
            moveCamera(new THREE.Vector3(c.x + 0.001, c.y + d, c.z + 0.001), new THREE.Vector3(c.x, c.y, c.z), false);
        },
        setAutoRotate(on) { controls.autoRotate = !!on; },
        setExploded(on) { explodeTarget = on ? 1 : 0; frame(focusId); },
        get storeys() { return levels.filter((l) => !l.outdoor).length; },
        get autoRotate() { return controls.autoRotate; },
        destroy() {
            running = false;
            cancelAnimationFrame(raf);
            ro.disconnect();
            io.disconnect();
            el.removeEventListener('pointerdown', onDown);
            el.removeEventListener('pointerup', onUp);
            el.removeEventListener('pointermove', onMove);
            el.removeEventListener('pointerleave', onLeave);
            controls.dispose();
            scene.traverse((o) => {
                if (o.geometry) o.geometry.dispose();
                if (o.material) [].concat(o.material).forEach((m) => { m.map?.dispose(); m.dispose(); });
            });
            envTex.dispose();
            pmrem.dispose();
            renderer.dispose();
            el.remove();
        },
    };
}
