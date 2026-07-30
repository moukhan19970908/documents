/**
 * Конструктор маршрута: схема процесса сверху вниз.
 *
 * Узлы хранятся деревом (у ветвящегося узла — цепочки `yes` и `no`), а раскладка
 * считается заново на каждое изменение: координаты карточек, соединители и точки
 * вставки «+». Шаблону остаются плоские списки — рекурсии в разметке нет.
 */

const NODE_W = 248;   // ширина карточки узла
const NODE_H = 56;    // высота карточки узла
const GAP_V = 44;     // вертикальный промежуток: в нём живут стрелка и «+»
const GAP_H = 32;     // промежуток между колонками веток
const PLUS_H = 28;    // высота пустой цепочки — там стоит одинокий «+»
const START_H = 40;   // карточка «Начало»
const PAD = 24;

let seq = 0;
const uid = () => 'n' + (++seq) + Date.now().toString(36);

export function scenarioGraph(initial) {
    return {
        types: initial.types,               // метаданные типов узлов
        palette: initial.palette,           // разделы палитры
        parameters: initial.parameters,     // параметры запуска — для условий
        users: initial.users,
        directions: initial.directions,
        departments: initial.departments,       // для условия по отделу инициатора
        departmentOperators: initial.departmentOperators,
        roles: initial.roles,
        statuses: initial.statuses,
        results: initial.results,
        recipients: initial.recipients,
        operators: initial.operators,

        nodes: prepare(initial.nodes || []),
        editing: null,                      // узел, открытый в модалке настроек
        insertAt: null,                     // выбранная точка вставки
        userSearch: '',

        // ── схема ────────────────────────────────────────────────────────────
        get layout() {
            const chain = measureChain(this.nodes, this.types);
            const cards = [];
            const edges = [];
            const pluses = [];

            const centerX = PAD + Math.max(chain.w, NODE_W) / 2;
            const startBottom = PAD + START_H;

            edges.push(vertical(centerX, startBottom, startBottom + GAP_V));
            placeChain(this.nodes, {
                centerX,
                top: startBottom + GAP_V,
                parent: null,
                branch: 'main',
                types: this.types,
                cards, edges, pluses,
            });

            // Соединители и стрелки собираются в два составных пути: так в разметке
            // не нужен цикл внутри <svg>, который браузер разбирает как SVG-элементы.
            return {
                width: Math.max(chain.w, NODE_W) + PAD * 2,
                height: startBottom + GAP_V + chain.h + PAD,
                startX: centerX - 70,
                linePath: edges.map(e => e.d).join(' '),
                arrowPath: edges.map(e => arrowhead(e.arrow)).join(' '),
                cards, pluses,
            };
        },

        // ── операции над схемой ──────────────────────────────────────────────
        /** Точка вставки: клик по «+» запоминает, куда положить следующий узел. */
        aim(target) {
            this.insertAt = this.sameTarget(target) ? null : target;
        },

        sameTarget(target) {
            const a = this.insertAt;
            return a && a.parent === target.parent && a.branch === target.branch && a.index === target.index;
        },

        addNode(type) {
            const node = {
                uid: uid(),
                type,
                name: this.types[type].label,
                config: defaultConfig(type),
                yes: [],
                no: [],
            };

            const target = this.insertAt || { parent: null, branch: 'main', index: this.nodes.length };
            const chain = this.chainOf(target.parent, target.branch);

            if (!chain) return;

            chain.splice(target.index, 0, node);
            this.insertAt = null;
            this.editing = node;
        },

        removeNode(uid) {
            const found = this.find(uid);
            if (!found) return;

            // Ветки уходят вместе с узлом: их шаги без развилки бессмысленны.
            found.chain.splice(found.index, 1);
            if (this.editing && this.editing.uid === uid) this.editing = null;
        },

        openNode(uid) {
            const found = this.find(uid);
            this.editing = found ? found.node : null;
            this.userSearch = '';
        },

        /** Цепочка по адресу: корневая или ветка узла. */
        chainOf(parentUid, branch) {
            if (!parentUid) return this.nodes;

            const found = this.find(parentUid);
            return found ? found.node[branch] : null;
        },

        find(uid, chain = null) {
            const list = chain || this.nodes;

            for (let i = 0; i < list.length; i++) {
                if (list[i].uid === uid) return { node: list[i], chain: list, index: i };

                for (const branch of ['yes', 'no']) {
                    const nested = this.find(uid, list[i][branch] || []);
                    if (nested) return nested;
                }
            }

            return null;
        },

        // ── подписи ──────────────────────────────────────────────────────────
        summary(node) {
            const cfg = node.config || {};

            if (node.type === 'condition') {
                if (cfg.source === 'initiator_department') {
                    const names = (cfg.department_ids || [])
                        .map(id => (this.departments.find(d => d.id === id) || {}).name)
                        .filter(Boolean);

                    return names.length
                        ? `отдел инициатора ${this.departmentOperators[cfg.condition_operator] || 'принадлежит'}: ${names.join(', ')}`
                        : 'отделы не выбраны';
                }

                const parameter = this.parameters.find(p => p.key === cfg.condition_key);
                return parameter
                    ? `${parameter.label} ${this.operators[cfg.condition_operator] || '='} ${cfg.condition_value || '—'}`
                    : 'условие не задано';
            }
            if (node.type === 'status') return this.statuses[cfg.status] || '—';
            if (node.type === 'end') return this.results[cfg.result] || '—';
            if (node.type === 'notify') return this.recipients[cfg.recipients] || '—';

            const people = (cfg.approver_ids || []).length;
            const groups = (cfg.group_department_ids || []).length;
            const parts = [];
            if (people) parts.push(`${people} ${plural(people, 'участник', 'участника', 'участников')}`);
            if (groups) parts.push(`${groups} ${plural(groups, 'направление', 'направления', 'направлений')}`);
            if (cfg.group_role) parts.push('роль');

            return parts.length ? parts.join(', ') : 'исполнители не назначены';
        },

        conditionOptions(key) {
            const parameter = this.parameters.find(p => p.key === key);
            return parameter ? (parameter.options || []) : [];
        },

        filteredUsers() {
            const q = this.userSearch.trim().toLowerCase();
            return q ? this.users.filter(u => u.name.toLowerCase().includes(q)) : this.users;
        },

        toggleId(list, id) {
            const at = list.indexOf(id);
            at === -1 ? list.push(id) : list.splice(at, 1);
        },

        userName(id) {
            return (this.users.find(u => u.id === id) || {}).name || '';
        },

        /** Схема уезжает на сервер одним полем — в том же виде, в каком нарисована. */
        serialize() {
            const clean = (chain) => chain.map(n => ({
                type: n.type,
                name: n.name,
                config: n.config,
                yes: clean(n.yes || []),
                no: clean(n.no || []),
            }));

            return JSON.stringify(clean(this.nodes));
        },
    };
}

// ── раскладка ────────────────────────────────────────────────────────────────

function branching(node, types) {
    return !!(types[node.type] || {}).branching;
}

function measureChain(chain, types) {
    let w = NODE_W;
    let h = 0;

    if (!chain.length) {
        return { w, h: PLUS_H };
    }

    chain.forEach(node => {
        const m = measureNode(node, types);
        w = Math.max(w, m.w);
        h += m.h + GAP_V;       // в промежутке после узла стоит «+»
    });

    return { w, h: h + PLUS_H };   // и ещё один «+» в конце цепочки
}

function measureNode(node, types) {
    if (!branching(node, types)) {
        return { w: NODE_W, h: NODE_H };
    }

    const yes = measureChain(node.yes || [], types);
    const no = measureChain(node.no || [], types);

    return {
        w: Math.max(NODE_W, yes.w + GAP_H + no.w),
        h: NODE_H + GAP_V + Math.max(yes.h, no.h),
    };
}

function placeChain(chain, ctx) {
    const { centerX, parent, branch, types, cards, edges, pluses } = ctx;
    let y = ctx.top;

    if (!chain.length) {
        pluses.push({ x: centerX, y: y + PLUS_H / 2, parent, branch, index: 0 });
        return y + PLUS_H;
    }

    chain.forEach((node, i) => {
        cards.push({
            uid: node.uid,
            node,
            x: centerX - NODE_W / 2,
            y,
            branching: branching(node, types),
        });

        let bottom = y + NODE_H;

        if (branching(node, types)) {
            const yesM = measureChain(node.yes || [], types);
            const noM = measureChain(node.no || [], types);
            const total = yesM.w + GAP_H + noM.w;
            const left = centerX - total / 2;
            const yesX = left + yesM.w / 2;
            const noX = left + yesM.w + GAP_H + noM.w / 2;
            const branchTop = y + NODE_H + GAP_V;

            // От карточки — вниз, вбок и снова вниз к первой карточке каждой ветки.
            edges.push(elbow(centerX, y + NODE_H, yesX, branchTop));
            edges.push(elbow(centerX, y + NODE_H, noX, branchTop));

            const yesBottom = placeChain(node.yes || [], { ...ctx, centerX: yesX, top: branchTop, parent: node.uid, branch: 'yes' });
            const noBottom = placeChain(node.no || [], { ...ctx, centerX: noX, top: branchTop, parent: node.uid, branch: 'no' });

            // Ветки сходятся обратно: маршрут продолжается тем, что нарисовано ниже развилки.
            const merge = branchTop + Math.max(yesM.h, noM.h);
            edges.push(elbow(yesX, yesBottom, centerX, merge, true));
            edges.push(elbow(noX, noBottom, centerX, merge, true));

            bottom = merge;
        }

        pluses.push({ x: centerX, y: bottom + GAP_V / 2, parent, branch, index: i + 1 });
        edges.push(vertical(centerX, bottom, bottom + GAP_V));

        y = bottom + GAP_V;
    });

    pluses.push({ x: centerX, y: y + PLUS_H / 2, parent, branch, index: chain.length });

    return y + PLUS_H;
}

/** Треугольник стрелки в конце соединителя. */
function arrowhead({ x, y }) {
    return `M ${x - 4} ${y - 7} L ${x + 4} ${y - 7} L ${x} ${y} Z`;
}

/** Прямой отрезок вниз со стрелкой на конце. */
function vertical(x, from, to) {
    return { d: `M ${x} ${from} L ${x} ${to}`, arrow: { x, y: to } };
}

/** Ступенька: вниз, вбок, вниз. */
function elbow(fromX, fromY, toX, toY, up = false) {
    const mid = up ? toY - GAP_V / 2 : fromY + GAP_V / 2;

    return {
        d: `M ${fromX} ${fromY} L ${fromX} ${mid} L ${toX} ${mid} L ${toX} ${toY}`,
        arrow: { x: toX, y: toY },
    };
}

// ── данные узла ──────────────────────────────────────────────────────────────

function defaultConfig(type) {
    switch (type) {
        case 'approval':
        case 'approve':
        case 'opinion':
        case 'ack':
        case 'intake':
            return {
                resolver: 'user', approver_ids: [], group_department_ids: [], group_role: '',
                policy: 'all', sla_days: 2, is_blocking: true, on_reject: 'return_initiator',
            };
        case 'condition':
            return {
                source: 'parameter', condition_key: '', condition_operator: '=', condition_value: '',
                department_ids: [],
            };
        case 'status':
            return { status: 'in_review' };
        case 'notify':
            return { recipients: 'initiator', user_ids: [], text: '' };
        case 'end':
            return { result: 'approved' };
        default:
            return {};
    }
}

/** Дереву с сервера нужны локальные идентификаторы и обе ветки у каждого узла. */
function prepare(chain) {
    return chain.map(node => ({
        uid: uid(),
        type: node.type,
        name: node.name,
        config: { ...defaultConfig(node.type), ...(node.config || {}) },
        yes: prepare(node.yes || []),
        no: prepare(node.no || []),
    }));
}

function plural(n, one, few, many) {
    const mod10 = n % 10;
    const mod100 = n % 100;

    if (mod10 === 1 && mod100 !== 11) return one;
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) return few;
    return many;
}
