// 3D restaurant: a glass-walled building you can orbit and look straight into.
// Built from the admin's floor plans: every Floor becomes a level, every table a real
// table with chairs. Loaded lazily (dynamic import) only on the reservations page.
import * as THREE from 'three';
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js';
import { RoomEnvironment } from 'three/examples/jsm/environments/RoomEnvironment.js';

const PX = 50;          // floor-plan pixels per world unit
const LEVEL_H = 3.4;    // vertical distance between levels
const WALL_H = 2.8;
const SLAB = 0.2;
const GOLD = 0xc9a45c;

// Table colours by availability state
const STATES = {
    idle:      { color: 0xe8dfca, emissive: 0x000000, ei: 0 },
    available: { color: 0x34d399, emissive: 0x059669, ei: 0.28 },
    booked:    { color: 0xf43f5e, emissive: 0x9f1239, ei: 0.3 },
    small:     { color: 0x6b7280, emissive: 0x000000, ei: 0 },
    selected:  { color: 0xfcd34d, emissive: 0xf59e0b, ei: 0.7 },
};

const isOutdoor = (name) => /patio|terrace|garden|outdoor|rooftop|deck/i.test(name || '');

/** Canvas-texture label that always faces the camera. */
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
    // ---------- renderer ----------
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
    controls.maxPolarAngle = Math.PI * 0.49;   // never go under the ground
    controls.minDistance = 4;
    controls.maxDistance = 70;
    controls.autoRotate = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    controls.autoRotateSpeed = 0.7;
    controls.screenSpacePanning = true;

    // ---------- lights ----------
    scene.add(new THREE.HemisphereLight(0xfff1d6, 0x1a1410, 0.55));
    const moon = new THREE.DirectionalLight(0x9db4ff, 0.35);
    moon.position.set(-12, 20, 10);
    scene.add(moon);

    // ---------- shared materials ----------
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

    // ---------- ground ----------
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

    // ---------- build levels ----------
    const levels = [];       // { group, floor, outdoor, y, W, D, cx, cz }
    const tables = new Map(); // tableId -> { floorId, top, chairs[], ring, group, label, state }
    const pickables = [];    // meshes for raycasting
    let maxW = 8, maxD = 6;

    const hasEnclosed = floors.some((f) => !isOutdoor(f.name));
    const mainDepth = (floors.find((f) => !isOutdoor(f.name))?.height ?? 0) / PX;
    let enclosedIdx = 0;
    let frontEdge = hasEnclosed ? mainDepth / 2 + 1.5 : 0;

    floors.forEach((floor) => {
        const W = floor.width / PX, D = floor.height / PX;
        maxW = Math.max(maxW, W);
        maxD = Math.max(maxD, D);
        const outdoor = isOutdoor(floor.name);
        const group = new THREE.Group();
        let y = 0, cz = 0;
        if (outdoor) {
            cz = frontEdge + D / 2;          // patios/terraces sit in front of the entrance
            frontEdge += D + 1.3;
        } else {
            y = enclosedIdx++ * LEVEL_H;      // enclosed floors stack as storeys
        }
        group.position.set(0, y, cz);
        scene.add(group);
        levels.push({ group, floor, outdoor, y, W, D, cx: 0, cz });

        // floor slab
        const slab = new THREE.Mesh(new THREE.BoxGeometry(W + 0.3, SLAB, D + 0.3), slabMat);
        slab.position.y = -SLAB / 2;
        group.add(slab);
        // wooden floor
        const floorTop = new THREE.Mesh(new THREE.BoxGeometry(W, 0.04, D), outdoor ? darkMat : woodMat);
        floorTop.position.y = 0.02;
        group.add(floorTop);
        const slabEdge = new THREE.LineSegments(new THREE.EdgesGeometry(slab.geometry), lineMat);
        slabEdge.position.copy(slab.position);
        group.add(slabEdge);

        if (!outdoor) buildGlassShell(group, W, D, y === 0, floor.name);
        else buildRailing(group, W, D);

        // warm interior light(s)
        const n = W > 12 ? 3 : 2;
        for (let i = 0; i < n; i++) {
            const l = new THREE.PointLight(0xffc98a, outdoor ? 14 : 22, 11, 1.6);
            l.position.set(((i + 0.5) / n - 0.5) * W * 0.8, WALL_H - 0.5, 0);
            group.add(l);
        }

        // tables
        floor.tables.forEach((t) => buildTable(group, floor, t));
        buildPlants(group, W, D);
    });

    function buildGlassShell(group, W, D, ground = true, title = '') {
        const H = WALL_H;
        const planes = [
            [W, H, [0, H / 2, D / 2], 0],           // front
            [W, H, [0, H / 2, -D / 2], 0],          // back
            [D, H, [W / 2, H / 2, 0], Math.PI / 2], // right
            [D, H, [-W / 2, H / 2, 0], Math.PI / 2],// left
        ];
        for (const [w, h, pos, rot] of planes) {
            const m = new THREE.Mesh(new THREE.PlaneGeometry(w, h), glassMat);
            m.position.set(...pos);
            m.rotation.y = rot;
            m.renderOrder = 10;
            group.add(m);
        }
        // glass roof
        const roof = new THREE.Mesh(new THREE.PlaneGeometry(W, D), glassMat);
        roof.rotation.x = -Math.PI / 2;
        roof.position.y = H;
        roof.renderOrder = 10;
        roof.userData.roof = true;
        group.add(roof);

        // gold frame: outline + corner posts + mullions
        const frame = new THREE.LineSegments(new THREE.EdgesGeometry(new THREE.BoxGeometry(W, H, D)), lineMat);
        frame.position.y = H / 2;
        group.add(frame);
        for (const sx of [-1, 1]) for (const sz of [-1, 1]) {
            const post = new THREE.Mesh(new THREE.BoxGeometry(0.1, H, 0.1), goldMat);
            post.position.set(sx * W / 2, H / 2, sz * D / 2);
            group.add(post);
        }
        const pts = [];
        const step = 2;
        for (let x = -W / 2 + step; x < W / 2 - 0.1; x += step) {
            pts.push(x, 0, D / 2, x, H, D / 2, x, 0, -D / 2, x, H, -D / 2);
        }
        for (let z = -D / 2 + step; z < D / 2 - 0.1; z += step) {
            pts.push(W / 2, 0, z, W / 2, H, z, -W / 2, 0, z, -W / 2, H, z);
        }
        const mull = new THREE.BufferGeometry();
        mull.setAttribute('position', new THREE.Float32BufferAttribute(pts, 3));
        group.add(new THREE.LineSegments(mull, new THREE.LineBasicMaterial({ color: GOLD, transparent: true, opacity: 0.35 })));
        // horizontal transom
        const tr = new THREE.Mesh(new THREE.BoxGeometry(W, 0.04, 0.04), goldMat);
        tr.position.set(0, H * 0.62, D / 2); group.add(tr);
        const tr2 = tr.clone(); tr2.position.z = -D / 2; group.add(tr2);

        // entrance (front, centre): door frame + sign
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

    function buildRailing(group, W, D) {
        const h = 1.0;
        const pts = [];
        const corners = [[-W / 2, -D / 2], [W / 2, -D / 2], [W / 2, D / 2], [-W / 2, D / 2]];
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
            for (const z of [-D / 2, D / 2]) {
                const p = new THREE.Mesh(new THREE.CylinderGeometry(0.03, 0.03, h, 8), goldMat);
                p.position.set(-W / 2 + (W * i) / posts, h / 2, z);
                group.add(p);
            }
        }
        for (const x of [-W / 2, W / 2]) {
            for (let i = 1; i < 4; i++) {
                const p = new THREE.Mesh(new THREE.CylinderGeometry(0.03, 0.03, h, 8), goldMat);
                p.position.set(x, h / 2, -D / 2 + (D * i) / 4);
                group.add(p);
            }
        }
        // string lights along the front rail
        for (let i = 0; i < 14; i++) {
            const b = new THREE.Mesh(new THREE.SphereGeometry(0.06, 8, 8), lampMat);
            b.position.set(-W / 2 + (W * (i + 0.5)) / 14, h + 0.25 + Math.sin(i * 1.3) * 0.06, D / 2);
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

        // base / legs
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

        // pendant lamp
        const cord = new THREE.Mesh(new THREE.CylinderGeometry(0.008, 0.008, 1.1, 4), darkMat);
        cord.position.y = WALL_H - 0.55;
        const bulb = new THREE.Mesh(new THREE.SphereGeometry(0.1, 12, 12), lampMat);
        bulb.position.y = 1.62;
        g.add(cord, bulb);

        // VIP: a burgundy rug with a gold edge marks the lounge seating
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

        // chairs
        const chairs = [];
        const chairMat = new THREE.MeshStandardMaterial({ color: STATES.idle.color, roughness: 0.6, metalness: 0.05 });
        const placeChair = (x, z, faceAngle) => {
            const c = new THREE.Group();
            const seat = new THREE.Mesh(new THREE.BoxGeometry(0.34, 0.06, 0.34), chairMat);
            seat.position.y = 0.44;
            const back = new THREE.Mesh(new THREE.BoxGeometry(0.34, 0.34, 0.05), chairMat);
            back.position.set(0, 0.64, 0.15);
            const stem = new THREE.Mesh(new THREE.CylinderGeometry(0.03, 0.04, 0.42, 8), darkMat);
            stem.position.y = 0.21;
            c.add(seat, back, stem);
            c.position.set(x, 0, z);
            c.rotation.y = faceAngle;      // back of chair faces away from the table
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

        // highlight ring on the floor
        const ringR = Math.max(tw, td) / 2 + (t.is_vip ? 1.1 : 0.75);
        const ring = new THREE.Mesh(
            new THREE.RingGeometry(ringR - 0.07, ringR, 48),
            new THREE.MeshBasicMaterial({ color: 0xfcd34d, transparent: true, opacity: 0.9, side: THREE.DoubleSide, depthWrite: false }),
        );
        ring.rotation.x = -Math.PI / 2;
        ring.position.y = 0.06;
        ring.visible = false;
        g.add(ring);

        // label
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

    // ---------- state colouring ----------
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

    // ---------- camera framing / focus ----------
    // Bounding box of everything, for framing the whole building.
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
    let explodeT = 0;        // eased 0..1 current separation
    let explodeTarget = 0;   // 0 = stacked, 1 = floors lifted apart

    function frame(focusFloorId, instant = false) {
        focusId = focusFloorId;
        let target, pos;
        const level = levels.find((l) => l.floor.id === focusFloorId);

        // Focusing a storey hides the storeys above it so you can look down inside.
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
            const d = (extent * 1.32 + H * 0.5) * (camera.aspect > 1.5 ? 0.88 : 1);   // wide screens: frame closer
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

    // ---------- interaction ----------
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
        if (e.buttons) return; // dragging
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

    // ---------- sizing & loop ----------
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
        // ease the storeys apart / back together
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

    // ---------- public API ----------
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
