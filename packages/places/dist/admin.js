// \0rolldown/runtime.js
var e = Object.create, t = Object.defineProperty, n = Object.getOwnPropertyDescriptor, r = Object.getOwnPropertyNames, i = Object.getPrototypeOf, a = Object.prototype.hasOwnProperty, o = (e, t, n) => () => {
	if (n) throw n[0];
	try {
		return e && (t = e(e = 0)), t;
	} catch (e) {
		throw n = [e], e;
	}
}, s = (e, t) => () => (t || (e((t = { exports: {} }).exports, t, t.exports), e = null), t.exports), c = (e, n) => {
	let r = {};
	for (var i in e) t(r, i, {
		get: e[i],
		enumerable: !0
	});
	return n || t(r, Symbol.toStringTag, { value: "Module" }), r;
}, l = (e, i, o, s) => {
	if (i && typeof i == "object" || typeof i == "function") for (var c = r(i), l = 0, u = c.length, d; l < u; l++) d = c[l], !a.call(e, d) && d !== o && t(e, d, {
		get: ((e) => i[e]).bind(null, d),
		enumerable: !(s = n(i, d)) || s.enumerable
	});
	return e;
}, u = (n, r, o) => (o = n == null ? {} : e(i(n)), l(r || !n || !n.__esModule || !a.call(n, "default") ? t(o, "default", {
	value: n,
	enumerable: !0
}) : o, n)), d = (e) => a.call(e, "module.exports") ? e["module.exports"] : l(t({}, "__esModule", { value: !0 }), e);
// node_modules/@studiometa/js-toolkit/dist/utils/memo.js
function f(e) {
	return typeof e == "object" && !!e || typeof e == "function";
}
function p(e) {
	let t, n, r = (...r) => {
		let i = r[0];
		return f(i) ? (n ??= /* @__PURE__ */ new WeakMap(), n.has(i) || n.set(i, e(...r)), n.get(i)) : (t ??= /* @__PURE__ */ new Map(), t.has(i) || t.set(i, e(...r)), t.get(i));
	};
	return r.clear = () => {
		t?.clear(), n = void 0;
	}, r;
}
// node_modules/@studiometa/js-toolkit/dist/utils/strings.js
var m = /([\p{Ll}\d])(\p{Lu})/gu, h = /(\p{Lu})([\p{Lu}][\p{Ll}])/gu, g = /[^\p{L}\d]+/giu, _ = "$1\0$2";
function v(e) {
	let t = e.trim().replace(m, _).replace(h, _).replace(g, "\0"), n = 0, r = t.length;
	for (; t.charAt(n) === "\0";) n++;
	if (n === r) return [];
	for (; t.charAt(r - 1) === "\0";) r--;
	return t.slice(n, r).split("\0");
}
function ee(e, t) {
	return v(e).map(te).join(t);
}
function te(e) {
	return e.toLowerCase();
}
function y(e) {
	return e.toUpperCase();
}
function b(e) {
	return y(e.charAt(0)) + e.slice(1);
}
var x = /* @__PURE__ */ p(function(e) {
	return ee(e, "-");
}), S = "data-component", ne = "data-mount", C = "data-ref", re = "data-option-";
function ie(e, t) {
	return `${e}:${t}`;
}
function ae(e, t) {
	return t === e || t?.startsWith(`${e}:`) === !0;
}
function oe(e, t) {
	let n = `${e}:`;
	return t.startsWith(n) ? t.slice(n.length) : null;
}
var se = [
	S,
	ne,
	C
];
function ce(e) {
	return `${re}${x(e)}`;
}
function le(e) {
	return `${re}no-${x(e)}`;
}
function ue(e) {
	return e?.startsWith(re) === !0;
}
function de(e) {
	return ae(S, e);
}
function fe(e, t, n) {
	e.has(t) || e.set(t, n);
}
function pe(e, t) {
	return e !== t;
}
// node_modules/@studiometa/js-toolkit/dist/shared-runtime.js
var me = Symbol.for("@studiometa/js-toolkit/runtime"), he = 1, ge = "[@studiometa/js-toolkit/runtime]";
function _e(e) {
	throw Error(`${ge} ${e}`);
}
function ve(e) {
	let t = e, n = t[me];
	if (n === void 0) {
		let e = {
			revision: he,
			slots: /* @__PURE__ */ new Map()
		};
		return t[me] = e, e;
	}
	return typeof n != "object" || !n || n.revision !== he || !(n.slots instanceof Map) ? _e("An incompatible value already owns globalThis[Symbol.for('@studiometa/js-toolkit/runtime')].") : n;
}
function w(e, t, n, r = globalThis) {
	let i = ve(r), a = i.slots.get(e);
	if (a) return a.revision === t ? a.value : _e(`Shared slot "${e}" has revision ${a.revision}; revision ${t} is required.`);
	let o = n();
	return i.slots.set(e, {
		revision: t,
		value: o
	}), o;
}
// node_modules/@studiometa/js-toolkit/dist/diagnostics.js
function ye() {
	return w("diagnostics:warnings", 1, () => ({ owners: /* @__PURE__ */ new WeakMap() })).owners;
}
function be(e, t) {
	let n;
	if (e.severity === "warning") {
		let t = `[js-toolkit:${e.code}] ${e.message}`;
		n = () => console.warn(t);
	} else {
		let t = e.error;
		n = () => reportError(t);
	}
	let r = new CustomEvent("js-toolkit:diagnostic", {
		bubbles: !0,
		composed: !0,
		cancelable: !0,
		detail: e
	});
	return (t?.isConnected ? t : document).dispatchEvent(r), r.defaultPrevented || n(), r;
}
function T(e, t, n, { component: r, target: i } = {}) {
	return be(r === void 0 ? {
		severity: "error",
		code: e,
		message: t,
		error: n
	} : {
		severity: "error",
		code: e,
		message: t,
		component: r,
		error: n
	}, i);
}
function xe(e, t, n) {
	try {
		n();
	} catch (n) {
		T(e, t, n);
	}
}
function Se(e, t, { component: n, target: r } = {}) {
	return be(n === void 0 ? {
		severity: "warning",
		code: e,
		message: t
	} : {
		severity: "warning",
		code: e,
		message: t,
		component: n
	}, r);
}
function E(e, t, n, r, i) {
	let a = ye(), o = a.get(e);
	o || (o = /* @__PURE__ */ new Set(), a.set(e, o));
	let s = `${n}\0${t}`;
	if (!o.has(s)) return o.add(s), Se(n, r, i);
}
// node_modules/@studiometa/js-toolkit/dist/scheduler.js
var Ce = 5, we = 1, Te = 40, Ee = 1e3 / 60, De = ["read", "write"], Oe = [], D;
function ke(e) {
	D || (D = new MessageChannel(), D.port1.onmessage = () => Oe.shift()?.()), Oe.push(e), D.port2.postMessage(null);
}
function Ae(e) {
	let t = globalThis.scheduler;
	typeof t?.postTask == "function" ? t.postTask(e, { priority: "background" }).catch((t) => {
		T("scheduler.background-post-failed", "The native scheduler rejected a background post.", t), ke(e);
	}) : ke(e);
}
var je = class {
	#e = {
		read: [],
		write: [],
		background: []
	};
	#t = "idle";
	#n = !1;
	#r = !1;
	#i = [];
	#a = /* @__PURE__ */ new Set();
	#o = -1;
	get phase() {
		return this.#t;
	}
	tick(e) {
		return this.#a.size === 0 && (this.#o = -1), this.#a.add(e), this.#l(), () => {
			this.#a.delete(e);
		};
	}
	read(e) {
		return this.#c("read", e);
	}
	write(e) {
		return this.#c("write", e);
	}
	background(e) {
		return this.#c("background", e);
	}
	whenIdle() {
		return this.#s() ? Promise.resolve() : new Promise((e) => this.#i.push(e));
	}
	#s() {
		return Object.values(this.#e).every((e) => e.length === 0);
	}
	#c(e, t) {
		let n, r, i = new Promise((e, t) => {
			n = e, r = t;
		});
		i.catch(() => {});
		let a = {
			fn: t,
			isCanceled: !1,
			resolve: n,
			reject: r
		};
		return this.#e[e].push(a), e === "background" ? this.#u() : this.#l(), {
			promise: i,
			cancel() {
				a.isCanceled = !0;
			}
		};
	}
	#l() {
		this.#n || (this.#n = !0, requestAnimationFrame((e) => this.#f(e)));
	}
	#u() {
		this.#r || (this.#r = !0, Ae(() => this.#d()));
	}
	#d() {
		this.#r = !1;
		let e = performance.now(), t = this.#e.background;
		for (this.#t = "background"; t.length > 0 && performance.now() - e < Ce;) this.#g(t.shift());
		this.#t = "idle", t.length > 0 && this.#u(), this.#h();
	}
	#f(e) {
		if (this.#n = !1, this.#a.size > 0) {
			this.#t = "tick";
			let t = {
				time: e,
				delta: this.#m(e)
			};
			this.#o = e;
			let n = [...this.#a];
			for (let e of n) if (this.#a.has(e)) try {
				e(t);
			} catch (e) {
				T("callback.scheduler-tick-failed", "A scheduler tick callback failed.", e);
			}
		}
		for (let e of De) {
			this.#t = e;
			let t = this.#e[e];
			this.#e[e] = [];
			for (let e of t) this.#g(e);
		}
		this.#t = "idle", (this.#p() || this.#a.size > 0) && this.#l(), this.#h();
	}
	#p() {
		return De.some((e) => this.#e[e].length > 0);
	}
	#m(e) {
		return this.#o < 0 ? Ee : Math.min(Math.max(e - this.#o, we), Te);
	}
	#h() {
		if (this.#i.length === 0 || !this.#s()) return;
		let e = this.#i;
		this.#i = [];
		for (let t of e) t();
	}
	#g(e) {
		if (e.isCanceled) e.resolve(void 0);
		else try {
			e.resolve(e.fn());
		} catch (t) {
			T("callback.scheduled-task-failed", "A scheduled task failed.", t), e.reject(t);
		}
	}
}, O = /* @__PURE__ */ w("scheduler:default", 1, () => new je()), k = /* @__PURE__ */ w("dom-mutations", 1, () => ({
	observedAttributes: new Set(se),
	attributeWatchers: /* @__PURE__ */ new Set(),
	observer: null,
	processor: null,
	processTask: null,
	version: 0,
	records: [],
	lifecycleWork: /* @__PURE__ */ new Set()
})), { observedAttributes: A, attributeWatchers: Me, lifecycleWork: Ne } = k;
function Pe() {
	return k.observer || (k.observer = new MutationObserver(j), Fe()), k.observer;
}
function Fe() {
	k.observer?.observe(document.documentElement, {
		childList: !0,
		subtree: !0,
		attributes: !0,
		attributeOldValue: !0,
		attributeFilter: [...A]
	});
}
function Ie(e) {
	return e !== null && A.has(e);
}
function Le(e, t) {
	if (e.length !== 0 || t.length !== 0) {
		j(k.observer?.takeRecords() ?? []);
		for (let t of e) A.delete(t);
		for (let e of t) A.add(e);
		k.observer && Fe();
	}
}
function Re(e) {
	Le([], [...e].filter((e) => !A.has(e)));
}
function ze(e, t) {
	let n = new Set(t);
	Le([...e].filter((e) => !n.has(e) && A.has(e)), [...n].filter((e) => !A.has(e)));
}
function Be() {
	for (let { pending: e } of Me) if (e.size > 0) return !0;
	return !1;
}
function Ve() {
	for (let e of Me) {
		if (e.pending.size === 0) continue;
		let t = e.pending;
		e.pending = /* @__PURE__ */ new Map();
		for (let [n, r] of t) {
			if (!Me.has(e)) break;
			let t = e.el.getAttribute(n);
			if (pe(t, r)) try {
				e.callback({
					name: n,
					value: t,
					previousValue: r
				});
			} catch (t) {
				T("callback.attribute-watcher-failed", `The attribute watcher callback for "${n}" failed.`, t, { target: e.el });
			}
		}
	}
}
function j(e) {
	let t = e.filter(({ type: e, attributeName: t }) => e === "childList" || Ie(t));
	t.length !== 0 && (t.some(({ type: e, attributeName: t }) => e === "childList" || de(t) || t === "data-ref") && (k.version += 1), k.processor && (k.records.push(...t.map((e) => {
		let t = /* @__PURE__ */ new Map();
		if (e.type === "childList") for (let n of e.removedNodes) n instanceof Element && t.set(n, [n, ...n.querySelectorAll("*")]);
		return {
			record: e,
			removedSubtrees: t
		};
	})), He()));
}
function He() {
	if (k.processTask || !(k.processor && k.records.length > 0) && !Be()) return;
	k.processTask = O.background(() => {
		let e = k.records;
		k.records = [], k.processor?.(e), Ve();
	});
	let e = () => {
		k.processTask = null, He();
	};
	k.processTask.promise.then(e, e);
}
function Ue(e) {
	k.processor ??= e, j(Pe().takeRecords()), He();
}
function We() {
	return j(Pe().takeRecords()), k.version;
}
function Ge(e) {
	if (!e) return;
	Ne.add(e);
	let t = () => Ne.delete(e);
	e.then(t, t);
}
// node_modules/@studiometa/js-toolkit/dist/services/service.js
function Ke({ props: e, start: t, hasProps: n }) {
	let r = /* @__PURE__ */ new Set(), i = "idle", a = null;
	function o(e) {
		let t = [...r];
		for (let n of t) n.isActive && xe("callback.service-failed", "A service subscriber failed.", () => n.callback(e));
	}
	return {
		props: e,
		subscribe(s, { immediate: c = !1 } = {}) {
			let l = {
				callback: s,
				isActive: !0
			};
			if (r.add(l), i === "idle") {
				i = "starting";
				try {
					a = t(o), i = "running";
				} catch (e) {
					for (let e of r) e.isActive = !1;
					throw r.clear(), a = null, i = "idle", e;
				}
			}
			return c && (n?.() ?? !0) && xe("callback.service-failed", "An immediate service subscriber failed.", () => s(e())), () => {
				if (!l.isActive || (l.isActive = !1, r.delete(l), r.size > 0) || i !== "running") return;
				let e = a;
				a = null, i = "idle", e?.();
			};
		}
	};
}
// node_modules/@studiometa/js-toolkit/dist/services/breakpoint.js
var qe = {
	xxs: "0rem",
	xs: "30rem",
	s: "48rem",
	m: "64rem",
	l: "80rem",
	xl: "90rem",
	xxl: "120rem",
	xxxl: "160rem"
}, M = /* @__PURE__ */ w("service:breakpoint", 1, () => ({
	breakpoints: { ...qe },
	queries: null,
	refresh: null,
	names: null,
	replacementListeners: /* @__PURE__ */ new Set(),
	widestMatch: p(() => {
		let e = "";
		for (let [t, n] of Xe()) n.matches && (e = t);
		return e;
	}),
	invalidating: !1,
	service: void 0
})), { replacementListeners: Je, widestMatch: Ye } = M;
function Xe() {
	return M.queries ??= Object.entries(M.breakpoints).map(([e, t]) => [e, window.matchMedia(`(min-width: ${t})`)]), M.queries;
}
function N() {
	return M.names ??= Object.keys(M.breakpoints), M.names;
}
function Ze(e) {
	return Je.add(e), () => {
		Je.delete(e);
	};
}
function Qe() {
	return M.invalidating || (M.invalidating = !0, queueMicrotask(() => {
		M.invalidating = !1, Ye.clear();
	})), Ye();
}
function $e() {
	let e = { name: "" };
	return Ke({
		props: () => (e.name = Qe(), e),
		start(t) {
			let n = "", r = () => {
				Ye.clear();
				let r = Qe();
				r !== n && (n = r, e.name = r, t(e));
			}, i = () => {
				let e = Xe();
				for (let [, t] of e) t.addEventListener("change", r);
				return () => {
					for (let [, t] of e) t.removeEventListener("change", r);
				};
			};
			n = Qe(), e.name = n;
			let a = i();
			return M.refresh = () => {
				a(), a = i(), r();
			}, () => {
				a(), M.refresh = null;
			};
		}
	});
}
function et() {
	return M.service ??= $e(), M.service;
}
// node_modules/@studiometa/js-toolkit/dist/events.js
var tt = Object.freeze({
	mounted: "js-toolkit:component:mounted",
	unmounted: "js-toolkit:component:unmounted"
}), nt = Object.freeze({ update: "js-toolkit:dom:update" }), P = Object.freeze({
	component: tt,
	dom: nt,
	diagnostic: "js-toolkit:diagnostic"
}), rt = "js-toolkit:context:request", { pendingRequests: F, rootProviders: it } = /* @__PURE__ */ w("context", 2, () => ({
	pendingRequests: /* @__PURE__ */ new Set(),
	rootProviders: /* @__PURE__ */ new Map()
}));
function at(e) {
	let t = !1, n, r = {
		key: e.key,
		subscribe: e.subscribe,
		provide(r) {
			t = !0, F.delete(e);
			let i = n.currentTarget;
			e.resolve(r, i !== null && "contains" in i ? i : null);
		}
	};
	return n = new CustomEvent(rt, {
		bubbles: !0,
		detail: r
	}), e.el.dispatchEvent(n), t;
}
function ot(e) {
	F.add(e);
}
function st(e) {
	F.delete(e);
}
function ct(e, t, n) {
	let r = (e) => {
		let { detail: r } = e;
		r?.key === t && (e.stopPropagation(), r.provide(n));
	};
	e.addEventListener(rt, r);
	for (let n of [...F]) n.key === t && e.contains(n.el) && at(n);
	return {
		value: n,
		dispose: () => e.removeEventListener(rt, r)
	};
}
function lt(e, t) {
	let n;
	return {
		promise: new Promise((r) => {
			n = {
				el: e,
				key: t,
				subscribe: !1,
				resolve: (e) => r(e)
			}, at(n) || ot(n);
		}),
		cancel: () => st(n)
	};
}
function ut(e, t) {
	let n;
	return at({
		el: e,
		key: t,
		subscribe: !1,
		resolve: (e) => {
			n = e;
		}
	}), n;
}
// node_modules/@studiometa/js-toolkit/dist/component-brand.js
var dt = Symbol.for("@studiometa/js-toolkit/base");
function I(e) {
	if (typeof e != "function") return !1;
	let t = e, n = t.config;
	return t[dt] === !0 && typeof t.prototype == "object" && t.prototype !== null && typeof n == "object" && !!n && typeof n.name == "string";
}
// node_modules/@studiometa/js-toolkit/dist/responsive-options.js
var ft = /* @__PURE__ */ w("responsive-options", 2, () => ({
	scopedAttributes: p((e) => N().map((t) => ie(e, t))),
	observed: /* @__PURE__ */ new Set(),
	isReplacementListenerAttached: !1
})), { scopedAttributes: L, observed: pt } = ft;
function mt(e) {
	return L(e);
}
ft.isReplacementListenerAttached || (ft.isReplacementListenerAttached = !0, Ze(() => {
	let e = [...pt].flatMap((e) => L(e));
	L.clear(), ze(e, [...pt].flatMap((e) => L(e)));
}));
function ht(e) {
	pt.add(e), Re(L(e));
}
function R() {
	return et().props().name;
}
function gt(e, t, n, r) {
	if (r === void 0) return _t(e, t, n) ?? n(e);
	let i = N(), a = L(e), o = L(r);
	for (let e = i.indexOf(t); e >= 0; --e) {
		let t = n(a[e]);
		if (t !== null) return t;
		if (n(o[e]) !== null) return "\0";
	}
	return n(e) ?? (n(r) === null ? null : "\0");
}
function _t(e, t, n) {
	let r = N(), i = L(e);
	for (let e = r.indexOf(t); e >= 0; --e) {
		let t = n(i[e]);
		if (t !== null) return t;
	}
	return null;
}
function vt(e) {
	let t = R();
	return et().subscribe(({ name: n }) => {
		let r = t;
		t = n, e(r);
	});
}
function yt(e, t) {
	if (t.length === 0) return;
	let n = N();
	for (let r of e.getAttributeNames()) for (let i of t) {
		let t = oe(i, r);
		if (t !== null && !n.includes(t)) {
			E(e, r, "responsive.unknown-breakpoint", `\`${r}\` names no breakpoint, so it is never read. One breakpoint per attribute, cascading upwards from it — known names: ${n.join(", ")}.`, { target: e });
			break;
		}
	}
}
// node_modules/@studiometa/js-toolkit/dist/component-declarations.js
function bt(e) {
	return (e ?? "").split(/\s+/).filter(Boolean);
}
function xt(e) {
	let t = (t) => e.getAttribute(t), n = _t(S, R(), t);
	return /* @__PURE__ */ new Set([...bt(t(S)), ...bt(n)]);
}
function St(e) {
	return e.getAttributeNames().some((e) => ae(S, e));
}
function Ct(e) {
	return mt(S).some((t) => e.hasAttribute(t));
}
// node_modules/@studiometa/js-toolkit/dist/children-watchers.js
var wt = /* @__PURE__ */ w("children-watchers", 1, () => ({
	watcherIndex: /* @__PURE__ */ new Set(),
	isUnmountListenerAttached: !1
})), { watcherIndex: Tt } = wt;
function Et(e) {
	let { instance: t } = e.detail;
	for (let e of [...Tt]) {
		let n = e.deref();
		n ? n(t) : Tt.delete(e);
	}
}
function Dt(e) {
	Tt.add(new WeakRef(e)), wt.isUnmountListenerAttached || (wt.isUnmountListenerAttached = !0, document.addEventListener(P.component.unmounted, Et));
}
// node_modules/@studiometa/js-toolkit/dist/document-order.js
function Ot(e, t) {
	let n = e.compareDocumentPosition(t);
	return n & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : n & Node.DOCUMENT_POSITION_PRECEDING ? 1 : 0;
}
// node_modules/@studiometa/js-toolkit/dist/protocol-symbols.js
var kt = Symbol.for("@studiometa/js-toolkit/handler-registrations"), z = Symbol.for("@studiometa/js-toolkit/instances");
// node_modules/@studiometa/js-toolkit/dist/utils/selectors.js
function At(e) {
	return [S, ...mt(S)].map((t) => `[${CSS.escape(t)}~="${e}"]`).join(",");
}
// node_modules/@studiometa/js-toolkit/dist/Base.js
var jt = /^on[A-Z]/, Mt = 0, Nt = /* @__PURE__ */ new Set([
	"blur",
	"focus",
	"load",
	"error",
	"scroll",
	"mouseenter",
	"mouseleave",
	"pointerenter",
	"pointerleave"
]);
function Pt(e, t, n) {
	n === void 0 || typeof n == "object" && n || E(e, t, "event.invalid-emit-payload", `\`$emit('${t}', …)\` takes one payload object; received ${typeof n}. Name the value: \`{ value }\`.`, {
		component: e.$config.name,
		target: e.$el
	});
}
function B(e, t, n) {
	let r = e.parentElement;
	for (; r && r !== t;) {
		let e = xt(r);
		if (n === void 0 ? e.size > 0 : e.has(n)) return !1;
		r = r.parentElement;
	}
	return r === t;
}
var Ft = "[]";
function It(e) {
	return e.endsWith(Ft);
}
function V(e) {
	return It(e) ? e.slice(0, -2) : e;
}
var Lt = ".";
function Rt(e, t) {
	return `${e}${Lt}${t}`;
}
function zt(e, t, n, r) {
	let i = e.getAttribute(C);
	return i === r ? B(e, t) : i === Rt(n, r) && B(e, t, n);
}
function Bt(e, t, n) {
	let r = [...e.querySelectorAll(`[${C}="${n}"]`)].filter((t) => B(t, e)), i = e.querySelectorAll(`[${C}="${Rt(t, n)}"]`);
	if (i.length === 0) return r;
	let a = [...i].filter((n) => B(n, e, t));
	return r.length === 0 ? a : [...r, ...a].sort(Ot);
}
function Vt(e, t, n) {
	if (n.has(t)) return;
	n.add(t);
	let r = V(t), i = Rt(e.$config.name, r);
	e.$el.querySelector(`[data-ref="${r}"],[data-ref="${i}"]`) && E(e, `attribute:${t}`, "ref.mismatch", `\`${e.$config.name}\` declares \`${t}\` and found no \`data-ref="${t}"\`, but the markup declares \`data-ref="${r}"\`. A list ref carries the \`[]\` in the attribute too: add it, or drop it from \`config.refs\`.`, {
		component: e.$config.name,
		target: e.$el
	});
}
function Ht(e, t, n) {
	let r = It(t) ? V(t) : `${t}${Ft}`;
	n.some(({ definition: e }) => e === r) && E(e, `handler:${t}`, "ref.mismatch", `\`${e.$config.name}\` binds \`@on('${t}', …)\`, which matches no component and no ref. The ref is declared \`${r}\`, and \`@on()\` names a ref the way it is declared: write \`@on('${r}', …)\`.`, {
		component: e.$config.name,
		target: e.$el
	});
}
function Ut(e) {
	let t = {}, n = /* @__PURE__ */ new Set(), r = e.$config.name;
	for (let i of e.$config.refs ?? []) {
		let a = It(i), o = V(i), s = -1, c = [];
		Object.defineProperty(t, o, {
			enumerable: !0,
			get() {
				let t;
				if (e.$el.isConnected) {
					let n = We();
					n !== s && (s = n, c = Bt(e.$el, r, i)), t = c;
				} else t = Bt(e.$el, r, i);
				return a && t.length === 0 && Vt(e, i, n), a ? t : t[0];
			}
		});
	}
	return t;
}
function Wt(e) {
	return `option${b(e)}Changed`;
}
function Gt(e, t) {
	let n = e[Wt(t)];
	return typeof n == "function" ? n : void 0;
}
function Kt(e, t, n, r, i) {
	E(n, "", "option.literal-default", `\`${e}\` declares option \`${t}\` with a literal ${Array.isArray(r) ? "array" : "object"} default, which every instance of it then shares. Only a primitive may be a default; declare this one as a factory: \`default: () => (…)\`.`, {
		component: e,
		target: i
	});
}
function H(e, t) {
	let n = e();
	return n === void 0 ? t : n;
}
function qt(e, t) {
	if (e === null) return H(t, void 0);
	try {
		return JSON.parse(e);
	} catch {
		return t();
	}
}
function Jt(e) {
	return e === Boolean ? !1 : e === Number ? 0 : e === String ? "" : e === Array ? [] : {};
}
function Yt(e, t) {
	return t === Boolean ? typeof e == "boolean" : t === Number ? typeof e == "number" && !Number.isNaN(e) : t === String ? typeof e == "string" : t === Array ? Array.isArray(e) : typeof e == "object" && !!e && !Array.isArray(e);
}
var Xt = /* @__PURE__ */ new Map([
	[Boolean, (e, t) => e === null ? t() ?? !1 : e !== "\0"],
	[Number, (e, t) => e === null ? H(t, 0) : Number(e)],
	[String, (e, t) => e === null ? H(t, "") : e],
	[Array, qt],
	[Object, qt]
]);
function Zt(e, t, n) {
	if (t === null) return H(n, e[0] === void 0 ? void 0 : Jt(e[0]));
	if (t === "\0") return !1;
	for (let n of e) {
		let e = Xt.get(n)(t, () => void 0);
		if (Yt(e, n)) return e;
	}
	return n();
}
function U(e) {
	return Array.isArray(e);
}
function Qt(e) {
	return typeof e == "function" || U(e);
}
function $t(e) {
	let t = Qt(e) ? e : e.type;
	return U(t) ? t.includes(Boolean) : t === Boolean;
}
function en(e) {
	let t = {}, n = /* @__PURE__ */ new Map(), r = e.$el;
	for (let [i, a] of Object.entries(e.$config.options ?? {})) {
		let o = Qt(a), s = o ? a : a.type, c = o ? void 0 : a.default, l = ce(i), u = $t(a) ? le(i) : void 0;
		!o && typeof c == "object" && c && Kt(e.$config.name, i, a, c, r);
		let d, f = !1, p = () => (f || (f = !0, d = m()), d), m = () => {
			if (typeof c == "function") return c();
			if (c !== void 0) return c;
			let e = U(s) ? s[0] : s;
			if (e === Array) return [];
			if (e === Object) return {};
		}, h = (e) => U(s) ? Zt(s, e, p) : (Xt.get(s) ?? ((e, t) => e === null ? H(t, void 0) : e))(e, p), g = (e) => r.getAttribute(e), _ = () => gt(l, R(), g, u);
		n.set(i, {
			attribute: l,
			rawValue: _,
			rawValueAt: (e, t) => gt(l, e, t, u),
			owns: (e) => ae(l, e) || u !== void 0 && ae(u, e),
			read: h
		}), Object.defineProperty(t, i, {
			enumerable: !0,
			get: () => h(_())
		});
	}
	return {
		options: t,
		readers: n
	};
}
var W = /* @__PURE__ */ p((e) => {
	let t = [], n = e;
	for (; n?.config;) Object.hasOwn(n, "config") && t.unshift(n.config), n = Object.getPrototypeOf(n);
	return t.reduce((e, t) => ({
		...e,
		...t,
		refs: [.../* @__PURE__ */ new Set([...e.refs ?? [], ...t.refs ?? []])],
		options: {
			...e.options,
			...t.options
		},
		components: {
			...e.components,
			...t.components
		}
	}), { name: e.name });
});
function G(e, t, n) {
	Object.defineProperty(e, t, {
		value: n,
		enumerable: !0
	});
}
var tn = ["Window", "Document"];
function nn(e) {
	for (let t of tn) if (e.length > t.length && e.startsWith(t)) return {
		target: t === "Window" ? window : document,
		type: x(e.slice(t.length))
	};
	return null;
}
function rn(e) {
	let t = /* @__PURE__ */ new Set(), n = e.prototype;
	for (; n && n !== ln.prototype;) {
		for (let e of Object.getOwnPropertyNames(n)) jt.test(e) && t.add(e);
		n = Object.getPrototypeOf(n);
	}
	return [...t];
}
var an = /* @__PURE__ */ p((e) => {
	let t = W(e), n = (e, t) => t.length - e.length, r = Object.keys(t.components ?? {}).sort(n), i = (t.refs ?? []).map((e) => ({
		name: V(e),
		definition: e
	})).sort((e, t) => n(e.name, t.name)), a = rn(e).map((e) => {
		let t = e.slice(2), n = nn(t);
		if (n) return {
			kind: "global",
			method: e,
			type: n.type,
			target: n.target
		};
		let a = (e) => t.startsWith(b(e)) && t.length > e.length, o = r.find(a);
		if (o) return {
			kind: "child",
			method: e,
			type: x(t.slice(o.length)),
			name: o
		};
		let s = i.find(({ name: e }) => a(e));
		return s ? {
			kind: "ref",
			method: e,
			type: x(t.slice(s.name.length)),
			name: s.definition
		} : {
			kind: "own",
			method: e,
			type: x(t)
		};
	});
	return {
		childNames: r,
		refs: i,
		componentName: t.name,
		entries: a
	};
});
function on(e, t, n) {
	e.has(t) || e.set(t, []), e.get(t)?.push(n);
}
function sn(e, t, n) {
	T("component.lifecycle-failed", t, n, {
		component: e.$config.name,
		target: e.$el
	});
}
var cn = Symbol("guard-failed"), ln = class {
	static [dt] = !0;
	static config = { name: "Base" };
	#e = !1;
	#t = [];
	#n = [];
	#r;
	#i = /* @__PURE__ */ new Map();
	#a = 0;
	#o = [];
	#s = /* @__PURE__ */ new Set();
	get $config() {
		return W(this.constructor);
	}
	get $isMounted() {
		return this.#e;
	}
	constructor(e) {
		let { name: t } = this.$config;
		Mt += 1, G(this, "$el", e), G(this, "$id", `${t}-${Mt - 1}`), e[z] ??= /* @__PURE__ */ new Map(), e[z].set(t, this);
		let n = en(this);
		G(this, "$options", n.options), this.#r = n.readers, G(this, "$refs", Ut(this));
	}
	mounted() {}
	unmounted() {}
	$mount() {
		if (this.#e) return this;
		this.#_(), this.#e = !0, this.#a += 1;
		let e = this.#a;
		if (this.#f(e), !this.#u(e) || (this.#p(e), !this.#u(e)) || (this.#d("`mounted()` failed.", () => this.#g(this.mounted(), e)), !this.#u(e))) return this;
		let t = { instance: this };
		return this.$el.dispatchEvent(new CustomEvent(P.component.mounted, {
			bubbles: !0,
			cancelable: !1,
			detail: t
		})), this;
	}
	$unmount() {
		if (!this.#e) return this;
		this.#e = !1;
		for (let [e, t, n, r] of this.#t) n.removeEventListener(e, t, r);
		this.#t = [];
		let e = this.#s;
		this.#s = /* @__PURE__ */ new Set();
		for (let t of e) t.cancel();
		let t = this.#n;
		this.#n = [];
		for (let e of t) this.#d("A mount cleanup failed.", e);
		this.#h(), this.#d("`unmounted()` failed.", () => this.unmounted());
		let n = { instance: this };
		return document.dispatchEvent(new CustomEvent(P.component.unmounted, {
			cancelable: !1,
			detail: n
		})), this;
	}
	$emit(e, t) {
		return Pt(this, e, t), this.#c(e, t);
	}
	#c(e, t) {
		let n = new CustomEvent(e, {
			bubbles: !0,
			cancelable: !0,
			detail: t
		});
		return this.$el.dispatchEvent(n), n;
	}
	$on(e, t, n) {
		return this.$el.addEventListener(e, t, n), () => this.$el.removeEventListener(e, t, n);
	}
	$off(e, t, n) {
		this.$el.removeEventListener(e, t, n);
	}
	$query(e) {
		return [...this.$el.querySelectorAll(At(e))].map((t) => t[z]?.get(e)).filter((e) => !!e?.$isMounted);
	}
	$closest(e) {
		let t = this.$el.parentElement;
		for (; t;) {
			let n = t[z]?.get(e);
			if (n?.$isMounted) return n;
			t = t.parentElement;
		}
		return null;
	}
	$watchChildren(e, t = {}) {
		let n = /* @__PURE__ */ new Set(), r = (t) => typeof e == "string" ? t.$config.name === e : t instanceof e, i = (e) => {
			e !== this && !n.has(e) && (n.add(e), t.added?.(e));
		}, a = (e) => {
			n.delete(e) && t.removed?.(e);
		};
		queueMicrotask(() => {
			if (typeof e == "string") for (let t of this.$query(e)) i(t);
			else for (let e of this.$el.querySelectorAll("*")) for (let t of e[z]?.values() ?? []) t.$isMounted && r(t) && i(t);
		});
		let o = (e) => {
			let { instance: t } = e.detail;
			t.$isMounted && r(t) && i(t);
		}, s = (e) => {
			r(e) && a(e);
		};
		return this.#o.push(s), Dt(s), this.$el.addEventListener(P.component.mounted, o), {
			get size() {
				return n.size;
			},
			get items() {
				return [...n].sort((e, t) => Ot(e.$el, t.$el));
			},
			[Symbol.iterator]() {
				return this.items[Symbol.iterator]();
			}
		};
	}
	$provide(e, t) {
		return ct(this.$el, e, t), t;
	}
	$inject(e) {
		let { promise: t, cancel: n } = lt(this.$el, e);
		return this.#n.push(n), t;
	}
	$injectSync(e) {
		return ut(this.$el, e);
	}
	$optionsChanged(e) {
		if (!this.#e) return;
		let t = (t) => e.has(t) ? e.get(t) ?? null : this.$el.getAttribute(t);
		for (let [n, r] of this.#r) {
			let i = e.has(r.attribute);
			if (!i) {
				for (let t of e.keys()) if (r.owns(t)) {
					i = !0;
					break;
				}
			}
			if (!i) continue;
			let a = r.rawValueAt(R(), t);
			pe(r.rawValue(), a) && this.#m(n, r, a, !1);
		}
	}
	$warn(e, t) {
		Se(e, t, {
			component: this.$config.name,
			target: this.$el
		});
	}
	$error(e, t, n) {
		T(e, t, n, {
			component: this.$config.name,
			target: this.$el
		});
	}
	$read(e) {
		return this.#l(O.read(e));
	}
	$write(e) {
		return this.#l(O.write(e));
	}
	#l(e) {
		this.#s.add(e);
		let t = () => this.#s.delete(e);
		return e.promise.then(t, t), e;
	}
	#u(e) {
		return this.#e && this.#a === e;
	}
	#d(e, t) {
		try {
			return t();
		} catch (t) {
			return sn(this, e, t), cn;
		}
	}
	#f(e) {
		for (let [t, n] of this.#r) {
			if (!this.#u(e)) return;
			this.#m(t, n, null, !0);
		}
	}
	#p(e) {
		let t = this.#r;
		t && t.size !== 0 && (yt(this.$el, [...t.values()].map((e) => e.attribute)), [...t.keys()].some((e) => Gt(this, e) !== void 0) && this.#g(vt((e) => {
			let n = (e) => this.$el.getAttribute(e);
			for (let [r, i] of t) {
				let t = i.rawValueAt(e, n);
				pe(i.rawValue(), t) && this.#m(r, i, t, !1);
			}
		}), e));
	}
	#m(e, t, n, r) {
		let i = this.#i.get(e);
		if (this.#i.delete(e), i && this.#d(`Option "${e}" cleanup failed.`, i), !this.#e) return;
		let a = Gt(this, e);
		if (!a) return;
		let o = this.#a, s = this.#d(`\`${Wt(e)}()\` failed.`, () => {
			let i = t.rawValue(), o = {
				name: e,
				value: t.read(i),
				previousValue: r ? void 0 : t.read(n),
				rawValue: i,
				previousRawValue: n,
				initial: r
			};
			return a.call(this, o);
		});
		s !== cn && typeof s == "function" && (this.#e && this.#a === o ? this.#i.set(e, s) : this.#d(`Option "${e}" cleanup failed.`, s));
	}
	#h() {
		let e = this.#i;
		this.#i = /* @__PURE__ */ new Map();
		for (let [t, n] of e) this.#d(`Option "${t}" cleanup failed.`, n);
	}
	#g(e, t) {
		if (typeof e == "function") this.#u(t) ? this.#n.push(e) : this.#d("A late mount cleanup failed.", e);
		else if (Array.isArray(e)) for (let n of e) this.#g(n, t);
		else e instanceof Promise && e.then((e) => this.#g(e, t)).catch((e) => {
			sn(this, "`mounted()` failed.", e);
		});
	}
	#_() {
		let e = an(this.constructor), t = /* @__PURE__ */ new Map(), n = this.#b(e, t);
		this.#x(e, t, n), this.#S(e.componentName, t);
	}
	#v(e, t) {
		let n = (e) => t(e);
		this.$el.addEventListener(e, n), this.#t.push([
			e,
			n,
			this.$el,
			!1
		]);
	}
	#y(e, t, n) {
		let r = (t) => n({
			event: t,
			target: e
		});
		e.addEventListener(t, r), this.#t.push([
			t,
			r,
			e,
			!1
		]);
	}
	#b(e, t) {
		let { childNames: n, refs: r } = e, i = this[kt] ?? [], a = new Set(i.map(({ method: e }) => e));
		for (let { target: e, child: a, type: o, handler: s } of i) if (e) this.#y(e, o, (e) => s.call(this, e));
		else if (a) {
			let e = n.includes(a), i = e ? void 0 : r.find(({ definition: e }) => e === a);
			!e && !i && Ht(this, a, r), on(t, o, {
				kind: i ? "ref" : "child",
				name: a,
				invoke: (e) => s.call(this, e)
			});
		} else this.#v(o, (e) => s.call(this, e));
		return a;
	}
	#x(e, t, n) {
		let r = this;
		for (let i of e.entries) {
			let { method: e, type: a } = i;
			n.has(e) || typeof r[e] == "function" && (i.kind === "global" ? this.#y(i.target, a, (t) => r[e](t)) : i.kind === "own" ? this.#v(a, (t) => r[e](t)) : on(t, a, {
				kind: i.kind,
				name: i.name,
				invoke: (t) => r[e](t)
			}));
		}
	}
	#S(e, t) {
		for (let [n, r] of t) {
			let t = (t) => {
				let n = t.target instanceof Element ? t.target : null;
				for (; n && n !== this.$el;) {
					let i = !1;
					for (let a of r) this.#C(a, n, t, e) && (i = !0);
					if (i) return;
					n = n.parentElement;
				}
			}, i = Nt.has(n);
			this.$el.addEventListener(n, t, i), this.#t.push([
				n,
				t,
				this.$el,
				i
			]);
		}
	}
	#C(e, t, n, r) {
		if (e.kind === "child") {
			let r = t[z]?.get(e.name);
			return r?.$isMounted ? (e.invoke({
				event: n,
				target: r,
				payload: n.detail
			}), !0) : !1;
		}
		return zt(t, this.$el, r, e.name) ? (e.invoke({
			event: n,
			target: t,
			index: Bt(this.$el, r, e.name).indexOf(t)
		}), !0) : !1;
	}
}, un = "page", dn = [
	"pointerenter",
	"pointerdown",
	"focusin"
], fn = [
	"pointerdown",
	"keydown",
	"focusin"
];
function pn(e) {
	let t = e.indexOf(":"), n = t < 0 ? e : e.slice(0, t), r = t < 0 ? "" : e.slice(t + 1);
	return n === "visible" || n === "in-view" ? {
		kind: "viewport",
		reversible: n === "in-view",
		rootMargin: r || void 0
	} : n === "media" && t >= 0 ? r.trim().length === 0 ? {
		kind: "invalid",
		error: /* @__PURE__ */ TypeError("The media mount strategy requires a non-empty query.")
	} : {
		kind: "media",
		query: r
	} : n === "interaction" ? r === "" || r === un ? {
		kind: "interaction",
		page: r === un
	} : {
		kind: "invalid",
		error: /* @__PURE__ */ TypeError(`The interaction mount strategy takes "${un}" or nothing, not "${r}".`)
	} : e === "eager" || e === "idle" ? { kind: e } : {
		kind: "invalid",
		error: /* @__PURE__ */ TypeError(`Unknown mount strategy "${e}".`)
	};
}
function mn(e) {
	let t = pn(e);
	return {
		eager: t.kind === "eager",
		reversible: t.kind === "media" || t.kind === "viewport" && t.reversible
	};
}
function hn(e) {
	return {
		valid: !1,
		dispose() {},
		error: e
	};
}
var K = /* @__PURE__ */ w("mount-strategy:page-interaction", 1, () => ({
	waiting: /* @__PURE__ */ new Set(),
	hasFired: !1,
	release: null
}));
function gn() {
	let e = () => {
		K.hasFired = !0, K.release?.();
		let e = [...K.waiting];
		K.waiting.clear();
		for (let t of e) t();
	};
	for (let t of fn) document.addEventListener(t, e, { capture: !0 });
	K.release = () => {
		K.release = null;
		for (let t of fn) document.removeEventListener(t, e, { capture: !0 });
	};
}
function _n(e) {
	return K.hasFired ? (e(), {
		valid: !0,
		dispose() {}
	}) : (K.waiting.add(e), K.release || gn(), {
		valid: !0,
		dispose() {
			K.waiting.delete(e), K.waiting.size === 0 && K.release?.();
		}
	});
}
function vn(e, t, { mount: n, unmount: r }) {
	let i = pn(t);
	if (i.kind === "invalid") return hn(i.error);
	if (i.kind === "viewport") {
		let t;
		try {
			t = new IntersectionObserver((e) => {
				for (let a of e) a.isIntersecting ? (i.reversible || t.disconnect(), n()) : i.reversible && r();
			}, i.rootMargin ? { rootMargin: i.rootMargin } : void 0);
		} catch (e) {
			return hn(e);
		}
		return t.observe(e), {
			valid: !0,
			dispose: () => t.disconnect()
		};
	}
	if (i.kind === "idle") {
		if (typeof requestIdleCallback != "function") {
			let e = O.background(n);
			return {
				valid: !0,
				dispose: () => e.cancel()
			};
		}
		let e = requestIdleCallback(() => n());
		return {
			valid: !0,
			dispose: () => cancelIdleCallback(e)
		};
	}
	if (i.kind === "interaction") {
		if (i.page) return _n(n);
		let t = () => {
			r(), n();
		}, r = () => {
			for (let n of dn) e.removeEventListener(n, t);
		};
		for (let n of dn) e.addEventListener(n, t, { once: !0 });
		return {
			valid: !0,
			dispose: r
		};
	}
	if (i.kind === "media") {
		let e = matchMedia(i.query), t = () => e.matches ? n() : r();
		return e.addEventListener("change", t), t(), {
			valid: !0,
			dispose: () => e.removeEventListener("change", t)
		};
	}
	let a = O.background(n);
	return {
		valid: !0,
		dispose: () => a.cancel(),
		eagerWork: a.promise
	};
}
// node_modules/@studiometa/js-toolkit/dist/registry.js
ht(S);
var q = /* @__PURE__ */ w("registry", 2, () => ({
	registry: /* @__PURE__ */ new Map(),
	controllers: /* @__PURE__ */ new WeakMap(),
	manifest: /* @__PURE__ */ new Map(),
	imports: /* @__PURE__ */ new Map(),
	responsiveElements: /* @__PURE__ */ new Set(),
	unwatchBreakpoints: null,
	responsiveTask: null,
	pendingResponsiveElements: /* @__PURE__ */ new Set(),
	isReplacementListenerAttached: !1
})), { registry: J, controllers: Y, manifest: X, imports: yn, responsiveElements: Z } = q;
function bn(e) {
	return Object.getOwnPropertyDescriptor(e, "prototype")?.writable === !1;
}
function xn(e) {
	let { name: t, components: n } = W(e);
	for (let [r, i] of Object.entries(n ?? {})) I(i) ? En(i) : typeof i == "function" && !bn(i) ? Sn(r, i) : E(e, r, "component.invalid-family-declaration", `"${t}" declares "${r}" as neither a component class nor an importer; the declaration was ignored.`, { component: t });
}
function Sn(e, t) {
	J.has(e) || X.has(e) || (X.set(e, { load: t }), Ue(Kn), zn(document.documentElement, e));
}
function Cn(e, t) {
	return xt(e).has(t);
}
function wn(e) {
	e.isConnected && Ct(e) ? (Z.add(e), q.unwatchBreakpoints ??= vt(() => {
		Tn(Z);
	})) : (Z.delete(e), Z.size === 0 && (q.unwatchBreakpoints?.(), q.unwatchBreakpoints = null));
}
function Tn(e) {
	for (let t of e) q.pendingResponsiveElements.add(t);
	if (q.responsiveTask || q.pendingResponsiveElements.size === 0) return;
	let t = O.background(() => {
		let e = q.pendingResponsiveElements;
		q.pendingResponsiveElements = /* @__PURE__ */ new Set();
		for (let t of e) t.isConnected ? Q(t) : wn(t);
	});
	q.responsiveTask = t, Ge(t.promise);
	let n = () => {
		q.responsiveTask = null, Tn([]);
	};
	t.promise.then(n, n);
}
q.isReplacementListenerAttached || (q.isReplacementListenerAttached = !0, Ze(() => {
	let e = new Set(Z), t = mt(S).map((e) => `[${CSS.escape(e)}]`).join(",");
	if (t) for (let n of document.querySelectorAll(t)) e.add(n);
	Tn(e);
}));
function En(e) {
	let { name: t } = W(e);
	J.has(t) ? J.get(t) !== e && E(e, t, "registry.conflict", `"${t}" is already registered; the incoming declaration was ignored.`, { component: t }) : (J.set(t, e), X.delete(t), xn(e), Re(kn(e)), Ue(Kn), zn(document.documentElement, t));
}
function Dn(e, t) {
	let n = yn.get(e);
	if (n) return n;
	let r = X.get(e);
	if (!r) return Promise.resolve();
	let i = Promise.resolve().then(() => r.load()).then((n) => {
		let i = On(n, e);
		if (!i) throw TypeError(`"${e}" did not resolve to a component class.`);
		let a = W(i).name;
		a !== e && E(r.load, e, "registry.lazy-name-mismatch", `"${e}" resolved to a component named "${a}".`, {
			component: e,
			target: t
		}), X.delete(e), En(i);
	}).catch((n) => {
		T("component.load-failed", `Failed to load component "${e}".`, n, {
			component: e,
			target: t
		});
	});
	return yn.set(e, i), i;
}
function On(e, t) {
	if (I(e)) return e;
	if (typeof e != "object" || !e) return;
	let n = e;
	for (let e of [n[t], n.default]) if (I(e)) return e;
}
function kn(e) {
	let t = [];
	for (let [n, r] of Object.entries(W(e).options ?? {})) {
		let e = ce(n);
		if (t.push(e), ht(e), $t(r)) {
			let e = le(n);
			t.push(e), ht(e);
		}
	}
	return t;
}
function An(e, t) {
	let n = I(t) ? W(t).mountStrategy : t.mountStrategy;
	return e.getAttribute("data-mount") ?? n ?? "eager";
}
function jn(e, t, n) {
	return n.state === "live" && Y.get(e)?.get(t) === n && e.isConnected && Cn(e, t) && An(e, n.source) === n.strategy;
}
function Mn(e, t, n, r) {
	if (!jn(e, t, r)) return;
	let i = e[z]?.get(t);
	try {
		i ||= new n(e), i.$mount();
	} catch (n) {
		i || e[z]?.delete(t), T("component.mount-failed", `Failed to mount component "${t}".`, n, {
			component: t,
			target: e
		});
	}
}
function Nn(e, t, n) {
	jn(e, t, n) && e[z]?.get(t)?.$unmount();
}
function Pn(e, t, n) {
	if (!jn(e, t, n)) return;
	n.state = "spent";
	let r = Dn(t, e);
	n.eager && Ge(r);
}
function Fn(e, t, n) {
	let { source: r } = n;
	return I(r) ? {
		mount: () => Mn(e, t, r, n),
		unmount: () => Nn(e, t, n)
	} : {
		mount: () => Pn(e, t, n),
		unmount() {}
	};
}
function In(e, t) {
	let n = Y.get(e), r = n?.get(t);
	r && (r.state = "disposed", r.dispose(), n?.delete(t), n?.size === 0 && Y.delete(e));
}
function Ln(e, t) {
	let n = J.get(t) ?? X.get(t);
	if (!n) return;
	let r = An(e, n), i = Y.get(e), a = i?.get(t);
	if (a?.strategy === r && a.source === n) return;
	let o = a?.state === "spent" && a.strategy === r;
	a && In(e, t), i = Y.get(e), i || (i = /* @__PURE__ */ new Map(), Y.set(e, i));
	let { eager: s, reversible: c } = mn(r), l = {
		dispose() {},
		eager: s,
		source: n,
		state: "live",
		strategy: r
	};
	i.set(t, l);
	let u = vn(e, r, Fn(e, t, l));
	l.dispose = u.dispose, u.valid || T("component.invalid-mount-strategy", `Failed to apply mount strategy "${r}" to component "${t}".`, u.error, {
		component: t,
		target: e
	}), Ge(u.eagerWork), o && !c && I(n) && (l.dispose(), Mn(e, t, n, l));
}
function Q(e) {
	yt(e, [S]), wn(e);
	let t = xt(e), n = /* @__PURE__ */ new Set([...Y.get(e)?.keys() ?? [], ...e[z]?.keys() ?? []]);
	for (let r of n) if (!t.has(r) && (J.has(r) || X.has(r))) {
		In(e, r);
		let t = e[z]?.get(r);
		t && (t.$unmount(), e[z]?.delete(r));
	}
	for (let n of t) Ln(e, n);
}
function Rn(e) {
	if (e instanceof Element) for (let t of [e, ...e.querySelectorAll("*")]) (St(t) || t[z] || Y.has(t)) && Q(t);
}
function zn(e, t) {
	let n = At(t), r = e.matches(n) ? [e, ...e.querySelectorAll(n)] : [...e.querySelectorAll(n)];
	for (let e of r) Q(e);
}
function Bn(e, t) {
	if (e instanceof Element) for (let n of t ?? [e, ...e.querySelectorAll("*")]) {
		wn(n);
		let e = Y.get(n);
		if (e) for (let t of e.keys()) In(n, t);
		if (n[z]) for (let e of n[z].values()) e.$unmount();
	}
}
function Vn(e) {
	for (let { record: t, removedSubtrees: n } of e) if (t.type === "childList") for (let e of t.removedNodes) Bn(e, n.get(e));
}
function Hn(e) {
	let t = /* @__PURE__ */ new Set(), n = /* @__PURE__ */ new Set(), r = /* @__PURE__ */ new Map();
	for (let { record: i } of e) if (i.type === "attributes" && i.target instanceof HTMLElement) {
		if (de(i.attributeName)) t.add(i.target);
		else if (i.attributeName === "data-mount") n.add(i.target);
		else if (ue(i.attributeName)) {
			let e = r.get(i.target);
			e || (e = /* @__PURE__ */ new Map(), r.set(i.target, e)), fe(e, i.attributeName, i.oldValue);
		}
	}
	return {
		declarations: t,
		strategies: n,
		options: r
	};
}
function Un({ declarations: e, strategies: t }) {
	for (let t of e) t.isConnected && Q(t);
	for (let n of t) n.isConnected && !e.has(n) && Q(n);
}
function Wn({ options: e }) {
	for (let [t, n] of e) if (t.isConnected) for (let e of t[z]?.values() ?? []) e.$isMounted && e.$optionsChanged(n);
}
function Gn(e) {
	for (let { record: t } of e) if (t.type === "childList") for (let e of t.addedNodes) e.isConnected && Rn(e);
}
function Kn(e) {
	Vn(e);
	let t = Hn(e);
	Un(t), Wn(t), Gn(e);
}
// node_modules/@studiometa/ui-mapbox/dist/AbstractMapboxMapChild.js
var qn = "mapbox-map:connected", Jn = class extends ln {
	static config = { name: "AbstractMapboxMapChild" };
	__readyMapboxMap;
	__readyMap;
	__readyCallback;
	__offMapReady;
	__offMapRemove;
	__offStyleReload;
	__offConnected;
	get mapboxMap() {
		let e = this.$closest("MapboxMap");
		return e || this.$warn("mapbox-map-child.orphan", "Can not find the parent map, does this component has a parent MapboxMap component?"), e;
	}
	get map() {
		return this.mapboxMap?.map;
	}
	whenMapReady(e) {
		this.__readyCallback = e, this.__resolveMap();
	}
	__resolveMap() {
		let e = this.$closest("MapboxMap");
		e ? this.__bindToMap(e) : this.__waitForConnectedMap();
	}
	__waitForConnectedMap() {
		if (this.__offConnected) return;
		let e = (e) => {
			e.detail?.$el?.contains(this.$el) && (this.__offConnected?.(), this.__offConnected = void 0, this.__resolveMap());
		};
		document.addEventListener(qn, e), this.__offConnected = () => document.removeEventListener(qn, e);
	}
	__bindToMap(e) {
		let t = e.map;
		if (!t) {
			this.__waitForConnectedMap();
			return;
		}
		this.__offMapReady?.(), this.__offMapReady = void 0, this.__bindMapRemove(t), this.__bindStyleReload(t);
		let n = () => {
			this.$isMounted && (this.__readyMapboxMap = e, this.__readyMap = t, this.__runReadyCallback(t));
		};
		e.isLoaded ? n() : this.__offMapReady = e.$on("map-load", () => {
			this.__offMapReady = void 0, n();
		}, { once: !0 });
	}
	__runReadyCallback(e) {
		let t;
		try {
			t = this.__readyCallback?.(e);
		} catch (e) {
			this.__handleError(e);
			return;
		}
		t && typeof t.then == "function" && Promise.resolve(t).catch((e) => this.__handleError(e));
	}
	__bindMapRemove(e) {
		if (this.__offMapRemove?.(), this.__offMapRemove = void 0, typeof e?.on != "function") return;
		let t = () => {
			this.__offMapRemove?.(), this.__offMapRemove = void 0;
			try {
				this.__onMapRemove(e);
			} catch (e) {
				this.__handleError(e);
			}
			this.__offMapReady?.(), this.__offMapReady = void 0, this.__offStyleReload?.(), this.__offStyleReload = void 0, this.__readyMap = void 0, this.__readyMapboxMap = void 0, this.$isMounted && this.__readyCallback && this.__waitForConnectedMap();
		};
		e.on("remove", t), this.__offMapRemove = () => e.off("remove", t);
	}
	__bindStyleReload(e) {
		if (this.__offStyleReload?.(), this.__offStyleReload = void 0, typeof e?.on != "function") return;
		let t = () => {
			this.$isMounted && this.__readyCallback && this.__readyMap === e && this.__runReadyCallback(e);
		};
		e.on("style.load", t), this.__offStyleReload = () => e.off("style.load", t);
	}
	__handleError(e) {
		this.$error("mapbox-map-child.failed", "A guarded map injection or teardown threw.", e), this.$emit("map-error", { error: e });
	}
	__onMapRemove(e) {}
	__onDestroyed() {}
	unmounted() {
		try {
			this.__onDestroyed();
		} catch (e) {
			this.__handleError(e);
		}
		this.__offMapReady?.(), this.__offMapReady = void 0, this.__offMapRemove?.(), this.__offMapRemove = void 0, this.__offStyleReload?.(), this.__offStyleReload = void 0, this.__offConnected?.(), this.__offConnected = void 0, this.__readyCallback = void 0;
	}
}, $ = /* @__PURE__ */ s(((e, t) => {
	t.exports = r;
	var n = Object.prototype.hasOwnProperty;
	function r() {
		for (var e = {}, t = 0; t < arguments.length; t++) {
			var r = arguments[t];
			for (var i in r) n.call(r, i) && (e[i] = r[i]);
		}
		return e;
	}
})), Yn = /* @__PURE__ */ s(((e, t) => {
	(function() {
		var n = this, r = {};
		e === void 0 ? n.fuzzy = r : t.exports = r, r.simpleFilter = function(e, t) {
			return t.filter(function(t) {
				return r.test(e, t);
			});
		}, r.test = function(e, t) {
			return r.match(e, t) !== null;
		}, r.match = function(e, t, n) {
			n ||= {};
			var r = 0, i = [], a = t.length, o = 0, s = 0, c = n.pre || "", l = n.post || "", u = n.caseSensitive && t || t.toLowerCase(), d;
			e = n.caseSensitive && e || e.toLowerCase();
			for (var f = 0; f < a; f++) d = t[f], u[f] === e[r] ? (d = c + d + l, r += 1, s += 1 + s) : s = 0, o += s, i[i.length] = d;
			return r === e.length ? (o = u === e ? Infinity : o, {
				rendered: i.join(""),
				score: o
			}) : null;
		}, r.filter = function(e, t, n) {
			return !t || t.length === 0 ? [] : typeof e == "string" ? (n ||= {}, t.reduce(function(t, i, a, o) {
				var s = i;
				n.extract && (s = n.extract(i));
				var c = r.match(e, s, n);
				return c != null && (t[t.length] = {
					string: c.rendered,
					score: c.score,
					index: a,
					original: i
				}), t;
			}, []).sort(function(e, t) {
				return t.score - e.score || e.index - t.index;
			})) : t;
		};
	})();
})), Xn = /* @__PURE__ */ s(((e, t) => {
	var n = function(e) {
		return this.component = e, this.items = [], this.active = 0, this.wrapper = document.createElement("div"), this.wrapper.className = "suggestions-wrapper", this.element = document.createElement("ul"), this.element.className = "suggestions", this.wrapper.appendChild(this.element), this.selectingListItem = !1, e.el.parentNode.insertBefore(this.wrapper, e.el.nextSibling), this;
	};
	n.prototype.show = function() {
		this.element.style.display = "block";
	}, n.prototype.hide = function() {
		this.element.style.display = "none";
	}, n.prototype.add = function(e) {
		this.items.push(e);
	}, n.prototype.clear = function() {
		this.items = [], this.active = 0;
	}, n.prototype.isEmpty = function() {
		return !this.items.length;
	}, n.prototype.isVisible = function() {
		return this.element.style.display === "block";
	}, n.prototype.draw = function() {
		if (this.element.innerHTML = "", this.items.length === 0) this.hide();
		else {
			for (var e = 0; e < this.items.length; e++) this.drawItem(this.items[e], this.active === e);
			this.show();
		}
	}, n.prototype.drawItem = function(e, t) {
		var n = document.createElement("li"), r = document.createElement("a");
		t && (n.className += " active"), r.innerHTML = e.string, n.appendChild(r), this.element.appendChild(n), n.addEventListener("mousedown", function() {
			this.selectingListItem = !0;
		}.bind(this)), n.addEventListener("mouseup", function() {
			this.handleMouseUp.call(this, e);
		}.bind(this));
	}, n.prototype.handleMouseUp = function(e) {
		this.selectingListItem = !1, this.component.value(e.original), this.clear(), this.draw();
	}, n.prototype.move = function(e) {
		this.active = e, this.draw();
	}, n.prototype.previous = function() {
		this.move(this.active === 0 ? this.items.length - 1 : this.active - 1);
	}, n.prototype.next = function() {
		this.move(this.active === this.items.length - 1 ? 0 : this.active + 1);
	}, n.prototype.drawError = function(e) {
		var t = document.createElement("li");
		t.innerHTML = e, this.element.appendChild(t), this.show();
	}, t.exports = n;
})), Zn = /* @__PURE__ */ s(((e, t) => {
	var n = $(), r = Yn(), i = Xn(), a = function(e, t, r) {
		return r ||= {}, this.options = n({
			minLength: 2,
			limit: 5,
			filter: !0,
			hideOnBlur: !0
		}, r), this.el = e, this.data = t || [], this.list = new i(this), this.query = "", this.selected = null, this.list.draw(), this.el.addEventListener("keyup", function(e) {
			this.handleKeyUp(e.keyCode);
		}.bind(this), !1), this.el.addEventListener("keydown", function(e) {
			this.handleKeyDown(e);
		}.bind(this)), this.el.addEventListener("focus", function() {
			this.handleFocus();
		}.bind(this)), this.el.addEventListener("blur", function() {
			this.handleBlur();
		}.bind(this)), this.el.addEventListener("paste", function(e) {
			this.handlePaste(e);
		}.bind(this)), this.render = this.options.render ? this.options.render.bind(this) : this.render.bind(this), this.getItemValue = this.options.getItemValue ? this.options.getItemValue.bind(this) : this.getItemValue.bind(this), this;
	};
	a.prototype.handleKeyUp = function(e) {
		e !== 40 && e !== 38 && e !== 27 && e !== 13 && e !== 9 && this.handleInputChange(this.el.value);
	}, a.prototype.handleKeyDown = function(e) {
		switch (e.keyCode) {
			case 13:
			case 9:
				this.list.isEmpty() || (this.list.isVisible() && e.preventDefault(), this.value(this.list.items[this.list.active].original), this.list.hide());
				break;
			case 27:
				this.list.isEmpty() || this.list.hide();
				break;
			case 38:
				this.list.previous();
				break;
			case 40: this.list.next();
		}
	}, a.prototype.handleBlur = function() {
		!this.list.selectingListItem && this.options.hideOnBlur && this.list.hide();
	}, a.prototype.handlePaste = function(e) {
		if (e.clipboardData) this.handleInputChange(e.clipboardData.getData("Text"));
		else {
			var t = this;
			setTimeout(function() {
				t.handleInputChange(e.target.value);
			}, 100);
		}
	}, a.prototype.handleInputChange = function(e) {
		this.query = this.normalize(e), this.list.clear(), this.query.length < this.options.minLength ? this.list.draw() : this.getCandidates(function(e) {
			for (var t = 0; t < e.length && (this.list.add(e[t]), t !== this.options.limit - 1); t++);
			this.list.draw();
		}.bind(this));
	}, a.prototype.handleFocus = function() {
		this.list.isEmpty() || this.list.show(), this.list.selectingListItem = !1;
	}, a.prototype.update = function(e) {
		this.data = e, this.handleKeyUp();
	}, a.prototype.clear = function() {
		this.data = [], this.list.clear();
	}, a.prototype.normalize = function(e) {
		return e = e.toLowerCase(), e;
	}, a.prototype.match = function(e, t) {
		return e.indexOf(t) > -1;
	}, a.prototype.value = function(e) {
		if (this.selected = e, this.el.value = this.getItemValue(e), document.createEvent) {
			var t = document.createEvent("HTMLEvents");
			t.initEvent("change", !0, !1), this.el.dispatchEvent(t);
		} else this.el.fireEvent("onchange");
	}, a.prototype.getCandidates = function(e) {
		var t = {
			pre: "<strong>",
			post: "</strong>",
			extract: function(e) {
				return this.getItemValue(e);
			}.bind(this)
		}, n;
		this.options.filter ? (n = r.filter(this.query, this.data, t), n = n.map(function(e) {
			return {
				original: e.original,
				string: this.render(e.original, e.string)
			};
		}.bind(this))) : n = this.data.map(function(e) {
			return {
				original: e,
				string: this.render(e)
			};
		}.bind(this)), e(n);
	}, a.prototype.getItemValue = function(e) {
		return e;
	}, a.prototype.render = function(e, t) {
		if (t) return t;
		for (var n = e.original ? this.getItemValue(e.original) : this.getItemValue(e), r = this.normalize(n), i = r.lastIndexOf(this.query); i > -1;) {
			var a = i + this.query.length;
			n = n.slice(0, i) + "<strong>" + n.slice(i, a) + "</strong>" + n.slice(a), i = r.slice(0, i).lastIndexOf(this.query);
		}
		return n;
	}, a.prototype.renderError = function(e) {
		this.list.drawError(e);
	}, t.exports = a;
})), Qn = /* @__PURE__ */ s(((e, t) => {
	var n = Zn();
	t.exports = n, typeof window < "u" && (window.Suggestions = n);
})), $n = /* @__PURE__ */ s(((e, t) => {
	var n = NaN, r = /^\s+|\s+$/g, i = /^[-+]0x[0-9a-f]+$/i, a = /^0b[01]+$/i, o = /^0o[0-7]+$/i, s = parseInt, c = typeof global == "object" && global && global.Object === Object && global, l = typeof self == "object" && self && self.Object === Object && self, u = c || l || Function("return this")(), d = Object.prototype.toString, f = Math.max, p = Math.min, m = function() {
		return u.Date.now();
	};
	function h(e, t, n) {
		var r, i, a, o, s, c, l = 0, u = !1, d = !1, h = !0;
		if (typeof e != "function") throw TypeError("Expected a function");
		t = ee(t) || 0, g(n) && (u = !!n.leading, d = "maxWait" in n, a = d ? f(ee(n.maxWait) || 0, t) : a, h = "trailing" in n ? !!n.trailing : h);
		function _(t) {
			var n = r, a = i;
			return r = i = void 0, l = t, o = e.apply(a, n), o;
		}
		function v(e) {
			return l = e, s = setTimeout(b, t), u ? _(e) : o;
		}
		function te(e) {
			var n = e - c, r = e - l, i = t - n;
			return d ? p(i, a - r) : i;
		}
		function y(e) {
			var n = e - c, r = e - l;
			return c === void 0 || n >= t || n < 0 || d && r >= a;
		}
		function b() {
			var e = m();
			if (y(e)) return x(e);
			s = setTimeout(b, te(e));
		}
		function x(e) {
			return s = void 0, h && r ? _(e) : (r = i = void 0, o);
		}
		function S() {
			s !== void 0 && clearTimeout(s), l = 0, r = c = i = s = void 0;
		}
		function ne() {
			return s === void 0 ? o : x(m());
		}
		function C() {
			var e = m(), n = y(e);
			if (r = arguments, i = this, c = e, n) {
				if (s === void 0) return v(c);
				if (d) return s = setTimeout(b, t), _(c);
			}
			return s === void 0 && (s = setTimeout(b, t)), o;
		}
		return C.cancel = S, C.flush = ne, C;
	}
	function g(e) {
		var t = typeof e;
		return !!e && (t == "object" || t == "function");
	}
	function _(e) {
		return !!e && typeof e == "object";
	}
	function v(e) {
		return typeof e == "symbol" || _(e) && d.call(e) == "[object Symbol]";
	}
	function ee(e) {
		if (typeof e == "number") return e;
		if (v(e)) return n;
		if (g(e)) {
			var t = typeof e.valueOf == "function" ? e.valueOf() : e;
			e = g(t) ? t + "" : t;
		}
		if (typeof e != "string") return e === 0 ? e : +e;
		e = e.replace(r, "");
		var c = a.test(e);
		return c || o.test(e) ? s(e.slice(2), c ? 2 : 8) : i.test(e) ? n : +e;
	}
	t.exports = h;
})), er = /* @__PURE__ */ s(((e, t) => {
	var n = typeof Reflect == "object" ? Reflect : null, r = n && typeof n.apply == "function" ? n.apply : function(e, t, n) {
		return Function.prototype.apply.call(e, t, n);
	}, i = n && typeof n.ownKeys == "function" ? n.ownKeys : Object.getOwnPropertySymbols ? function(e) {
		return Object.getOwnPropertyNames(e).concat(Object.getOwnPropertySymbols(e));
	} : function(e) {
		return Object.getOwnPropertyNames(e);
	};
	function a(e) {
		console && console.warn && console.warn(e);
	}
	var o = Number.isNaN || function(e) {
		return e !== e;
	};
	function s() {
		s.init.call(this);
	}
	t.exports = s, t.exports.once = ee, s.EventEmitter = s, s.prototype._events = void 0, s.prototype._eventsCount = 0, s.prototype._maxListeners = void 0;
	var c = 10;
	function l(e) {
		if (typeof e != "function") throw TypeError("The \"listener\" argument must be of type Function. Received type " + typeof e);
	}
	Object.defineProperty(s, "defaultMaxListeners", {
		enumerable: !0,
		get: function() {
			return c;
		},
		set: function(e) {
			if (typeof e != "number" || e < 0 || o(e)) throw RangeError("The value of \"defaultMaxListeners\" is out of range. It must be a non-negative number. Received " + e + ".");
			c = e;
		}
	}), s.init = function() {
		(this._events === void 0 || this._events === Object.getPrototypeOf(this)._events) && (this._events = Object.create(null), this._eventsCount = 0), this._maxListeners = this._maxListeners || void 0;
	}, s.prototype.setMaxListeners = function(e) {
		if (typeof e != "number" || e < 0 || o(e)) throw RangeError("The value of \"n\" is out of range. It must be a non-negative number. Received " + e + ".");
		return this._maxListeners = e, this;
	};
	function u(e) {
		return e._maxListeners === void 0 ? s.defaultMaxListeners : e._maxListeners;
	}
	s.prototype.getMaxListeners = function() {
		return u(this);
	}, s.prototype.emit = function(e) {
		for (var t = [], n = 1; n < arguments.length; n++) t.push(arguments[n]);
		var i = e === "error", a = this._events;
		if (a !== void 0) i &&= a.error === void 0;
		else if (!i) return !1;
		if (i) {
			var o;
			if (t.length > 0 && (o = t[0]), o instanceof Error) throw o;
			var s = /* @__PURE__ */ Error("Unhandled error." + (o ? " (" + o.message + ")" : ""));
			throw s.context = o, s;
		}
		var c = a[e];
		if (c === void 0) return !1;
		if (typeof c == "function") r(c, this, t);
		else for (var l = c.length, u = g(c, l), n = 0; n < l; ++n) r(u[n], this, t);
		return !0;
	};
	function d(e, t, n, r) {
		var i, o, s;
		if (l(n), o = e._events, o === void 0 ? (o = e._events = Object.create(null), e._eventsCount = 0) : (o.newListener !== void 0 && (e.emit("newListener", t, n.listener ? n.listener : n), o = e._events), s = o[t]), s === void 0) s = o[t] = n, ++e._eventsCount;
		else if (typeof s == "function" ? s = o[t] = r ? [n, s] : [s, n] : r ? s.unshift(n) : s.push(n), i = u(e), i > 0 && s.length > i && !s.warned) {
			s.warned = !0;
			var c = /* @__PURE__ */ Error("Possible EventEmitter memory leak detected. " + s.length + " " + String(t) + " listeners added. Use emitter.setMaxListeners() to increase limit");
			c.name = "MaxListenersExceededWarning", c.emitter = e, c.type = t, c.count = s.length, a(c);
		}
		return e;
	}
	s.prototype.addListener = function(e, t) {
		return d(this, e, t, !1);
	}, s.prototype.on = s.prototype.addListener, s.prototype.prependListener = function(e, t) {
		return d(this, e, t, !0);
	};
	function f() {
		if (!this.fired) return this.target.removeListener(this.type, this.wrapFn), this.fired = !0, arguments.length === 0 ? this.listener.call(this.target) : this.listener.apply(this.target, arguments);
	}
	function p(e, t, n) {
		var r = {
			fired: !1,
			wrapFn: void 0,
			target: e,
			type: t,
			listener: n
		}, i = f.bind(r);
		return i.listener = n, r.wrapFn = i, i;
	}
	s.prototype.once = function(e, t) {
		return l(t), this.on(e, p(this, e, t)), this;
	}, s.prototype.prependOnceListener = function(e, t) {
		return l(t), this.prependListener(e, p(this, e, t)), this;
	}, s.prototype.removeListener = function(e, t) {
		var n, r, i, a, o;
		if (l(t), r = this._events, r === void 0 || (n = r[e], n === void 0)) return this;
		if (n === t || n.listener === t) --this._eventsCount === 0 ? this._events = Object.create(null) : (delete r[e], r.removeListener && this.emit("removeListener", e, n.listener || t));
		else if (typeof n != "function") {
			for (i = -1, a = n.length - 1; a >= 0; a--) if (n[a] === t || n[a].listener === t) {
				o = n[a].listener, i = a;
				break;
			}
			if (i < 0) return this;
			i === 0 ? n.shift() : _(n, i), n.length === 1 && (r[e] = n[0]), r.removeListener !== void 0 && this.emit("removeListener", e, o || t);
		}
		return this;
	}, s.prototype.off = s.prototype.removeListener, s.prototype.removeAllListeners = function(e) {
		var t, n = this._events, r;
		if (n === void 0) return this;
		if (n.removeListener === void 0) return arguments.length === 0 ? (this._events = Object.create(null), this._eventsCount = 0) : n[e] !== void 0 && (--this._eventsCount === 0 ? this._events = Object.create(null) : delete n[e]), this;
		if (arguments.length === 0) {
			var i = Object.keys(n), a;
			for (r = 0; r < i.length; ++r) a = i[r], a !== "removeListener" && this.removeAllListeners(a);
			return this.removeAllListeners("removeListener"), this._events = Object.create(null), this._eventsCount = 0, this;
		}
		if (t = n[e], typeof t == "function") this.removeListener(e, t);
		else if (t !== void 0) for (r = t.length - 1; r >= 0; r--) this.removeListener(e, t[r]);
		return this;
	};
	function m(e, t, n) {
		var r = e._events;
		if (r === void 0) return [];
		var i = r[t];
		return i === void 0 ? [] : typeof i == "function" ? n ? [i.listener || i] : [i] : n ? v(i) : g(i, i.length);
	}
	s.prototype.listeners = function(e) {
		return m(this, e, !0);
	}, s.prototype.rawListeners = function(e) {
		return m(this, e, !1);
	}, s.listenerCount = function(e, t) {
		return typeof e.listenerCount == "function" ? e.listenerCount(t) : h.call(e, t);
	}, s.prototype.listenerCount = h;
	function h(e) {
		var t = this._events;
		if (t !== void 0) {
			var n = t[e];
			if (typeof n == "function") return 1;
			if (n !== void 0) return n.length;
		}
		return 0;
	}
	s.prototype.eventNames = function() {
		return this._eventsCount > 0 ? i(this._events) : [];
	};
	function g(e, t) {
		for (var n = Array(t), r = 0; r < t; ++r) n[r] = e[r];
		return n;
	}
	function _(e, t) {
		for (; t + 1 < e.length; t++) e[t] = e[t + 1];
		e.pop();
	}
	function v(e) {
		for (var t = Array(e.length), n = 0; n < t.length; ++n) t[n] = e[n].listener || e[n];
		return t;
	}
	function ee(e, t) {
		return new Promise(function(n, r) {
			function i(n) {
				e.removeListener(t, a), r(n);
			}
			function a() {
				typeof e.removeListener == "function" && e.removeListener("error", i), n([].slice.call(arguments));
			}
			y(e, t, a, { once: !0 }), t !== "error" && te(e, i, { once: !0 });
		});
	}
	function te(e, t, n) {
		typeof e.on == "function" && y(e, "error", t, n);
	}
	function y(e, t, n, r) {
		if (typeof e.on == "function") r.once ? e.once(t, n) : e.on(t, n);
		else if (typeof e.addEventListener == "function") e.addEventListener(t, function i(a) {
			r.once && e.removeEventListener(t, i), n(a);
		});
		else throw TypeError("The \"emitter\" argument must be of type EventEmitter. Received type " + typeof e);
	}
})), tr = /* @__PURE__ */ s(((e, t) => {
	t.exports = {
		fr: {
			name: "France",
			bbox: [[-4.59235, 41.380007], [9.560016, 51.148506]]
		},
		us: {
			name: "United States",
			bbox: [[-171.791111, 18.91619], [-66.96466, 71.357764]]
		},
		ru: {
			name: "Russia",
			bbox: [[19.66064, 41.151416], [190.10042, 81.2504]]
		},
		ca: {
			name: "Canada",
			bbox: [[-140.99778, 41.675105], [-52.648099, 83.23324]]
		}
	};
})), nr = /* @__PURE__ */ s(((e, t) => {
	function n(e) {
		var t = e.match(/\s*(.+)\s*=\s*"?([^"]+)"?/);
		return t ? {
			key: t[1],
			value: t[2]
		} : null;
	}
	function r(e) {
		var t = e.match(/<?([^>]*)>(.*)/);
		if (!t) return null;
		var r = t[1], i = t[2].split(";"), a = null, o = i.reduce(function(e, t) {
			var r = n(t);
			return r ? r.key === "rel" ? (a ||= r.value, e) : (e[r.key] = r.value, e) : e;
		}, {});
		return a ? {
			url: r,
			rel: a,
			params: o
		} : null;
	}
	function i(e) {
		return e ? e.split(/,\s*</).reduce(function(e, t) {
			var n = r(t);
			return n && n.rel.split(/\s+/).forEach(function(t) {
				e[t] || (e[t] = {
					url: n.url,
					params: n.params
				});
			}), e;
		}, {}) : {};
	}
	t.exports = i;
})), rr = /* @__PURE__ */ s(((e, t) => {
	var n = nr();
	function r(e, t) {
		this.request = e, this.headers = t.headers, this.rawBody = t.body, this.statusCode = t.statusCode;
		try {
			this.body = JSON.parse(t.body || "{}");
		} catch {
			this.body = t.body;
		}
		this.links = n(this.headers.link);
	}
	r.prototype.hasNextPage = function() {
		return !!this.links.next;
	}, r.prototype.nextPage = function() {
		return this.hasNextPage() ? this.request._extend({ path: this.links.next.url }) : null;
	}, t.exports = r;
})), ir = /* @__PURE__ */ s(((e, t) => {
	t.exports = {
		API_ORIGIN: "https://api.mapbox.com",
		EVENT_PROGRESS_DOWNLOAD: "downloadProgress",
		EVENT_PROGRESS_UPLOAD: "uploadProgress",
		EVENT_ERROR: "error",
		EVENT_RESPONSE: "response",
		ERROR_HTTP: "HttpError",
		ERROR_REQUEST_ABORTED: "RequestAbortedError"
	};
})), ar = /* @__PURE__ */ s(((e, t) => {
	var n = ir();
	function r(e) {
		var t = e.type || n.ERROR_HTTP, r;
		if (e.body) try {
			r = JSON.parse(e.body);
		} catch {
			r = e.body;
		}
		else r = null;
		var i = e.message || null;
		i || (typeof r == "string" ? i = r : r && typeof r.message == "string" ? i = r.message : t === n.ERROR_REQUEST_ABORTED && (i = "Request aborted")), this.message = i, this.type = t, this.statusCode = e.statusCode || null, this.request = e.request, this.body = r;
	}
	t.exports = r;
})), or = /* @__PURE__ */ s(((e, t) => {
	function n(e) {
		var t = e.indexOf(":");
		return {
			name: e.substring(0, t).trim().toLowerCase(),
			value: e.substring(t + 1).trim()
		};
	}
	function r(e) {
		var t = {};
		return e && e.trim().split(/[\r|\n]+/).forEach(function(e) {
			var r = n(e);
			t[r.name] = r.value;
		}), t;
	}
	t.exports = r;
})), sr = /* @__PURE__ */ s(((e, t) => {
	var n = rr(), r = ar(), i = ir(), a = or(), o = {};
	function s(e) {
		var t = o[e.id];
		t && (t.abort(), delete o[e.id]);
	}
	function c(e, t) {
		return new n(e, {
			body: t.response,
			headers: a(t.getAllResponseHeaders()),
			statusCode: t.status
		});
	}
	function l(e) {
		var t = e.total, n = e.loaded;
		return {
			total: t,
			transferred: n,
			percent: 100 * n / t
		};
	}
	function u(e, t) {
		return new Promise(function(n, a) {
			t.onprogress = function(t) {
				e.emitter.emit(i.EVENT_PROGRESS_DOWNLOAD, l(t));
			};
			var s = e.file;
			s && (t.upload.onprogress = function(t) {
				e.emitter.emit(i.EVENT_PROGRESS_UPLOAD, l(t));
			}), t.onerror = function(e) {
				a(e);
			}, t.onabort = function() {
				a(new r({
					request: e,
					type: i.ERROR_REQUEST_ABORTED
				}));
			}, t.onload = function() {
				delete o[e.id], t.status < 200 || t.status >= 400 ? a(new r({
					request: e,
					body: t.response,
					statusCode: t.status
				})) : n(t);
			};
			var c = e.body;
			typeof c == "string" ? t.send(c) : c ? t.send(JSON.stringify(c)) : s ? t.send(s) : t.send(), o[e.id] = t;
		}).then(function(t) {
			return c(e, t);
		});
	}
	function d(e, t) {
		var n = e.url(t), r = new window.XMLHttpRequest();
		return r.open(e.method, n), Object.keys(e.headers).forEach(function(t) {
			r.setRequestHeader(t, e.headers[t]);
		}), r;
	}
	function f(e) {
		return Promise.resolve().then(function() {
			return u(e, d(e, e.client.accessToken));
		});
	}
	t.exports = {
		browserAbort: s,
		sendRequestXhr: u,
		browserSend: f,
		createRequestXhr: d
	};
})), cr = /* @__PURE__ */ s(((e, t, n) => {
	(function(n) {
		var r = typeof e == "object" && e, i = typeof t == "object" && t && t.exports == r && t, a = typeof global == "object" && global;
		(a.global === a || a.window === a) && (n = a);
		var o = function(e) {
			this.message = e;
		};
		o.prototype = /* @__PURE__ */ Error(), o.prototype.name = "InvalidCharacterError";
		var s = function(e) {
			throw new o(e);
		}, c = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/", l = /[\t\n\f\r ]/g, u = {
			encode: function(e) {
				e = String(e), /[^\0-\xFF]/.test(e) && s("The string to be encoded contains characters outside of the Latin1 range.");
				for (var t = e.length % 3, n = "", r = -1, i, a, o, l, u = e.length - t; ++r < u;) i = e.charCodeAt(r) << 16, a = e.charCodeAt(++r) << 8, o = e.charCodeAt(++r), l = i + a + o, n += c.charAt(l >> 18 & 63) + c.charAt(l >> 12 & 63) + c.charAt(l >> 6 & 63) + c.charAt(l & 63);
				return t == 2 ? (i = e.charCodeAt(r) << 8, a = e.charCodeAt(++r), l = i + a, n += c.charAt(l >> 10) + c.charAt(l >> 4 & 63) + c.charAt(l << 2 & 63) + "=") : t == 1 && (l = e.charCodeAt(r), n += c.charAt(l >> 2) + c.charAt(l << 4 & 63) + "=="), n;
			},
			decode: function(e) {
				e = String(e).replace(l, "");
				var t = e.length;
				t % 4 == 0 && (e = e.replace(/==?$/, ""), t = e.length), (t % 4 == 1 || /[^+a-zA-Z0-9/]/.test(e)) && s("Invalid character: the string to be decoded is not correctly encoded.");
				for (var n = 0, r, i, a = "", o = -1; ++o < t;) i = c.indexOf(e.charAt(o)), r = n % 4 ? r * 64 + i : i, n++ % 4 && (a += String.fromCharCode(255 & r >> (-2 * n & 6)));
				return a;
			},
			version: "0.1.0"
		};
		if (typeof define == "function" && typeof define.amd == "object" && define.amd) define(function() {
			return u;
		});
		else if (r && !r.nodeType) {
			if (i) i.exports = u;
			else for (var d in u) u.hasOwnProperty(d) && (r[d] = u[d]);
		} else n.base64 = u;
	})(n);
})), lr = /* @__PURE__ */ s(((e, t) => {
	var n = cr(), r = {};
	function i(e) {
		if (r[e]) return r[e];
		var t = e.split("."), n = t[0], i = t[1];
		if (!i) throw Error("Invalid token");
		var s = a(i), c = {
			usage: n,
			user: s.u
		};
		return o(s, "a") && (c.authorization = s.a), o(s, "exp") && (c.expires = s.exp * 1e3), o(s, "iat") && (c.created = s.iat * 1e3), o(s, "scopes") && (c.scopes = s.scopes), o(s, "client") && (c.client = s.client), o(s, "ll") && (c.lastLogin = s.ll), o(s, "iu") && (c.impersonator = s.iu), r[e] = c, c;
	}
	function a(e) {
		try {
			return JSON.parse(n.decode(e));
		} catch {
			throw Error("Invalid token");
		}
	}
	function o(e, t) {
		return Object.prototype.hasOwnProperty.call(e, t);
	}
	t.exports = i;
})), ur = /* @__PURE__ */ s(((e, t) => {
	var n = Object.prototype.hasOwnProperty, r = "~";
	function i() {}
	Object.create && (i.prototype = Object.create(null), new i().__proto__ || (r = !1));
	function a(e, t, n) {
		this.fn = e, this.context = t, this.once = n || !1;
	}
	function o(e, t, n, i, o) {
		if (typeof n != "function") throw TypeError("The listener must be a function");
		var s = new a(n, i || e, o), c = r ? r + t : t;
		return e._events[c] ? e._events[c].fn ? e._events[c] = [e._events[c], s] : e._events[c].push(s) : (e._events[c] = s, e._eventsCount++), e;
	}
	function s(e, t) {
		--e._eventsCount === 0 ? e._events = new i() : delete e._events[t];
	}
	function c() {
		this._events = new i(), this._eventsCount = 0;
	}
	c.prototype.eventNames = function() {
		var e = [], t, i;
		if (this._eventsCount === 0) return e;
		for (i in t = this._events) n.call(t, i) && e.push(r ? i.slice(1) : i);
		return Object.getOwnPropertySymbols ? e.concat(Object.getOwnPropertySymbols(t)) : e;
	}, c.prototype.listeners = function(e) {
		var t = r ? r + e : e, n = this._events[t];
		if (!n) return [];
		if (n.fn) return [n.fn];
		for (var i = 0, a = n.length, o = Array(a); i < a; i++) o[i] = n[i].fn;
		return o;
	}, c.prototype.listenerCount = function(e) {
		var t = r ? r + e : e, n = this._events[t];
		return n ? n.fn ? 1 : n.length : 0;
	}, c.prototype.emit = function(e, t, n, i, a, o) {
		var s = r ? r + e : e;
		if (!this._events[s]) return !1;
		var c = this._events[s], l = arguments.length, u, d;
		if (c.fn) {
			switch (c.once && this.removeListener(e, c.fn, void 0, !0), l) {
				case 1: return c.fn.call(c.context), !0;
				case 2: return c.fn.call(c.context, t), !0;
				case 3: return c.fn.call(c.context, t, n), !0;
				case 4: return c.fn.call(c.context, t, n, i), !0;
				case 5: return c.fn.call(c.context, t, n, i, a), !0;
				case 6: return c.fn.call(c.context, t, n, i, a, o), !0;
			}
			for (d = 1, u = Array(l - 1); d < l; d++) u[d - 1] = arguments[d];
			c.fn.apply(c.context, u);
		} else {
			var f = c.length, p;
			for (d = 0; d < f; d++) switch (c[d].once && this.removeListener(e, c[d].fn, void 0, !0), l) {
				case 1:
					c[d].fn.call(c[d].context);
					break;
				case 2:
					c[d].fn.call(c[d].context, t);
					break;
				case 3:
					c[d].fn.call(c[d].context, t, n);
					break;
				case 4:
					c[d].fn.call(c[d].context, t, n, i);
					break;
				default:
					if (!u) for (p = 1, u = Array(l - 1); p < l; p++) u[p - 1] = arguments[p];
					c[d].fn.apply(c[d].context, u);
			}
		}
		return !0;
	}, c.prototype.on = function(e, t, n) {
		return o(this, e, t, n, !1);
	}, c.prototype.once = function(e, t, n) {
		return o(this, e, t, n, !0);
	}, c.prototype.removeListener = function(e, t, n, i) {
		var a = r ? r + e : e;
		if (!this._events[a]) return this;
		if (!t) return s(this, a), this;
		var o = this._events[a];
		if (o.fn) o.fn !== t || i && !o.once || n && o.context !== n || s(this, a);
		else {
			for (var c = 0, l = [], u = o.length; c < u; c++) (o[c].fn !== t || i && !o[c].once || n && o[c].context !== n) && l.push(o[c]);
			l.length ? this._events[a] = l.length === 1 ? l[0] : l : s(this, a);
		}
		return this;
	}, c.prototype.removeAllListeners = function(e) {
		var t;
		return e ? (t = r ? r + e : e, this._events[t] && s(this, t)) : (this._events = new i(), this._eventsCount = 0), this;
	}, c.prototype.off = c.prototype.removeListener, c.prototype.addListener = c.prototype.on, c.prefixed = r, c.EventEmitter = c, t !== void 0 && (t.exports = c);
})), dr = /* @__PURE__ */ s(((e, t) => {
	function n(e) {
		return e.map(encodeURIComponent).join(",");
	}
	function r(e) {
		return Array.isArray(e) ? n(e) : encodeURIComponent(String(e));
	}
	function i(e, t, n) {
		if (n === !1 || n === null) return e;
		var i = /\?/.test(e) ? "&" : "?", a = encodeURIComponent(t);
		return n !== void 0 && n !== "" && n !== !0 && (a += "=" + r(n)), "" + e + i + a;
	}
	function a(e, t) {
		if (!t) return e;
		var n = e;
		return Object.keys(t).forEach(function(e) {
			var r = t[e];
			r !== void 0 && (Array.isArray(r) && (r = r.filter(function(e) {
				return e != null;
			}).join(",")), n = i(n, e, r));
		}), n;
	}
	function o(e, t) {
		if (!t || e.slice(0, 4) === "http") return e;
		var n = e[0] === "/" ? "" : "/";
		return "" + t.replace(/\/$/, "") + n + e;
	}
	function s(e, t) {
		return t ? e.replace(/\/:([a-zA-Z0-9]+)/g, function(e, n) {
			var i = t[n];
			if (i === void 0) throw Error("Unspecified route parameter " + n);
			return "/" + r(i);
		}) : e;
	}
	t.exports = {
		appendQueryObject: a,
		appendQueryParam: i,
		prependOrigin: o,
		interpolateRouteParams: s
	};
})), fr = /* @__PURE__ */ s(((e, t) => {
	var n = lr(), r = $(), i = ur(), a = dr(), o = ir(), s = 1;
	function c(e, t) {
		if (!e) throw Error("MapiRequest requires a client");
		if (!t || !t.path || !t.method) throw Error("MapiRequest requires an options object with path and method properties");
		var n = {};
		t.body && (n["content-type"] = "application/json");
		var a = r(n, t.headers), o = Object.keys(a).reduce(function(e, t) {
			return e[t.toLowerCase()] = a[t], e;
		}, {});
		this.id = s++, this._options = t, this.emitter = new i(), this.client = e, this.response = null, this.error = null, this.sent = !1, this.aborted = !1, this.path = t.path, this.method = t.method, this.origin = t.origin || e.origin, this.query = t.query || {}, this.params = t.params || {}, this.body = t.body || null, this.file = t.file || null, this.encoding = t.encoding || "utf8", this.sendFileAs = t.sendFileAs || null, this.headers = o;
	}
	c.prototype.url = function(e) {
		var t = a.prependOrigin(this.path, this.origin);
		t = a.appendQueryObject(t, this.query);
		var i = this.params, o = e ?? this.client.accessToken;
		if (o) {
			t = a.appendQueryParam(t, "access_token", o);
			var s = n(o).user;
			i = r({ ownerId: s }, i);
		}
		return t = a.interpolateRouteParams(t, i), t;
	}, c.prototype.send = function() {
		var e = this;
		if (e.sent) throw Error("This request has already been sent. Check the response and error properties. Create a new request with clone().");
		return e.sent = !0, e.client.sendRequest(e).then(function(t) {
			return e.response = t, e.emitter.emit(o.EVENT_RESPONSE, t), t;
		}, function(t) {
			throw e.error = t, e.emitter.emit(o.EVENT_ERROR, t), t;
		});
	}, c.prototype.abort = function() {
		this._nextPageRequest && (this._nextPageRequest.abort(), delete this._nextPageRequest), this.response || this.error || this.aborted || (this.aborted = !0, this.client.abortRequest(this));
	}, c.prototype.eachPage = function(e) {
		var t = this;
		function n(n) {
			function r() {
				delete t._nextPageRequest;
				var e = n.nextPage();
				e && (t._nextPageRequest = e, i(e));
			}
			e(null, n, r);
		}
		function r(t) {
			e(t, null, function() {});
		}
		function i(e) {
			e.send().then(n, r);
		}
		i(this);
	}, c.prototype.clone = function() {
		return this._extend();
	}, c.prototype._extend = function(e) {
		var t = r(this._options, e);
		return new c(this.client, t);
	}, t.exports = c;
})), pr = /* @__PURE__ */ s(((e, t) => {
	var n = lr(), r = fr(), i = ir();
	function a(e) {
		if (!e || !e.accessToken) throw Error("Cannot create a client without an access token");
		n(e.accessToken), this.accessToken = e.accessToken, this.origin = e.origin || i.API_ORIGIN;
	}
	a.prototype.createRequest = function(e) {
		return new r(this, e);
	}, t.exports = a;
})), mr = /* @__PURE__ */ s(((e, t) => {
	var n = sr(), r = pr();
	function i(e) {
		r.call(this, e);
	}
	i.prototype = Object.create(r.prototype), i.prototype.constructor = i, i.prototype.sendRequest = n.browserSend, i.prototype.abortRequest = n.browserAbort;
	function a(e) {
		return new i(e);
	}
	t.exports = a;
})), hr = /* @__PURE__ */ s(((e, t) => {
	t.exports = mr();
})), gr = /* @__PURE__ */ s(((e, t) => {
	var n = Object.prototype.toString;
	t.exports = function(e) {
		var t;
		return n.call(e) === "[object Object]" && (t = Object.getPrototypeOf(e), t === null || t === Object.getPrototypeOf({}));
	};
})), _r = /* @__PURE__ */ s(((e, t) => {
	var n = gr(), r = $(), i = "value", a = "\n  ", o = {};
	o.assert = function(e, t) {
		return t ||= {}, function(n) {
			var r = c(e, n);
			if (r) {
				var i = l(r, t);
				throw t.apiName && (i = t.apiName + ": " + i), Error(i);
			}
		};
	}, o.shape = function(e) {
		var t = h(e);
		return function(e) {
			var n = c(o.plainObject, e);
			if (n) return n;
			for (var r, s, u = [], d = 0; d < t.length; d++) r = t[d].key, s = t[d].value, n = c(s, e[r]), n && u.push([r].concat(n));
			return u.length < 2 ? u[0] : function(e) {
				u = u.map(function(t) {
					var n = t[0], r = l(t, e).split("\n").join(a);
					return "- " + n + ": " + r;
				});
				var t = e.path.join(".");
				return "The following properties" + (t === i ? "" : " of " + t) + " have invalid values:" + a + u.join(a);
			};
		};
	}, o.strictShape = function(e) {
		var t = o.shape(e);
		return function(n) {
			var r = t(n);
			if (r) return r;
			var i = Object.keys(n).reduce(function(t, n) {
				return e[n] === void 0 && t.push(n), t;
			}, []);
			if (i.length !== 0) return function() {
				return "The following keys are invalid: " + i.join(", ");
			};
		};
	}, o.arrayOf = function(e) {
		return s(e);
	}, o.tuple = function() {
		return s(Array.isArray(arguments[0]) ? arguments[0] : Array.prototype.slice.call(arguments));
	};
	function s(e) {
		var t = Array.isArray(e), n = function(n) {
			return t ? e[n] : e;
		};
		return function(r) {
			var i = c(o.plainArray, r);
			if (i) return i;
			if (t && r.length !== e.length) return "an array with " + e.length + " items";
			for (var a = 0; a < r.length; a++) if (i = c(n(a), r[a]), i) return [a].concat(i);
		};
	}
	o.required = function(e) {
		function t(t) {
			return t == null ? function(e) {
				return p(e, m(e.path) ? "cannot be undefined/null." : "is required.");
			} : e.apply(this, arguments);
		}
		return t.__required = !0, t;
	}, o.oneOfType = function() {
		var e = Array.isArray(arguments[0]) ? arguments[0] : Array.prototype.slice.call(arguments);
		return function(t) {
			var n = e.map(function(e) {
				return c(e, t);
			}).filter(Boolean);
			if (n.length === e.length) return n.every(function(e) {
				return e.length === 1 && typeof e[0] == "string";
			}) ? u(n.map(function(e) {
				return e[0];
			})) : n.reduce(function(e, t) {
				return t.length > e.length ? t : e;
			});
		};
	}, o.equal = function(e) {
		return function(t) {
			if (t !== e) return JSON.stringify(e);
		};
	}, o.oneOf = function() {
		var e = (Array.isArray(arguments[0]) ? arguments[0] : Array.prototype.slice.call(arguments)).map(function(e) {
			return o.equal(e);
		});
		return o.oneOfType.apply(this, e);
	}, o.range = function(e) {
		var t = e[0], n = e[1];
		return function(e) {
			if (c(o.number, e) || e < t || e > n) return "number between " + t + " & " + n + " (inclusive)";
		};
	}, o.any = function() {}, o.boolean = function(e) {
		if (typeof e != "boolean") return "boolean";
	}, o.number = function(e) {
		if (typeof e != "number") return "number";
	}, o.plainArray = function(e) {
		if (!Array.isArray(e)) return "array";
	}, o.plainObject = function(e) {
		if (!n(e)) return "object";
	}, o.string = function(e) {
		if (typeof e != "string") return "string";
	}, o.func = function(e) {
		if (typeof e != "function") return "function";
	};
	function c(e, t) {
		if (t != null || e.hasOwnProperty("__required")) {
			var n = e(t);
			if (n) return Array.isArray(n) ? n : [n];
		}
	}
	function l(e, t) {
		var n = e.length, a = e[n - 1], o = e.slice(0, n - 1);
		return o.length === 0 && (o = [i]), t = r(t, { path: o }), typeof a == "function" ? a(t) : p(t, d(a));
	}
	function u(e) {
		return e.length < 2 ? e[0] : e.length === 2 ? e.join(" or ") : e.slice(0, -1).join(", ") + ", or " + e.slice(-1);
	}
	function d(e) {
		return "must be " + f(e) + ".";
	}
	function f(e) {
		return /^an? /.test(e) ? e : /^[aeiou]/i.test(e) ? "an " + e : /^[a-z]/i.test(e) ? "a " + e : e;
	}
	function p(e, t) {
		var n = m(e.path), r = e.path.join(".") + " " + t;
		return (n ? "Item at position " : "") + r;
	}
	function m(e) {
		return typeof e[e.length - 1] == "number" || typeof e[0] == "number";
	}
	function h(e) {
		return Object.keys(e || {}).map(function(t) {
			return {
				key: t,
				value: e[t]
			};
		});
	}
	o.validate = c, o.processMessage = l, t.exports = o;
})), vr = /* @__PURE__ */ s(((e, t) => {
	var n = $(), r = _r();
	function i(e) {
		if (typeof window < "u") return e instanceof global.Blob || e instanceof global.ArrayBuffer ? void 0 : "Blob or ArrayBuffer";
		if (typeof e != "string" && e.pipe === void 0) return "Filename or Readable stream";
	}
	function a(e, t) {
		return r.assert(r.strictShape(e), t);
	}
	function o(e) {
		var t = "date";
		if (typeof e == "boolean") return t;
		try {
			var n = new Date(e);
			if (n.getTime && isNaN(n.getTime())) return t;
		} catch {
			return t;
		}
	}
	function s(e) {
		return r.tuple(r.number, r.number)(e);
	}
	t.exports = n(r, {
		file: i,
		date: o,
		coordinates: s,
		assertShape: a
	});
})), yr = /* @__PURE__ */ s(((e, t) => {
	function n(e, t) {
		var n = function(e, n) {
			return t.indexOf(e) !== -1 && n !== void 0;
		};
		return typeof t == "function" && (n = t), Object.keys(e).filter(function(t) {
			return n(t, e[t]);
		}).reduce(function(t, n) {
			return t[n] = e[n], t;
		}, {});
	}
	t.exports = n;
})), br = /* @__PURE__ */ s(((e, t) => {
	function n(e, t) {
		return Object.keys(e).reduce(function(n, r) {
			return n[r] = t(r, e[r]), n;
		}, {});
	}
	t.exports = n;
})), xr = /* @__PURE__ */ s(((e, t) => {
	var n = br();
	function r(e) {
		return n(e, function(e, t) {
			return typeof t == "boolean" ? JSON.stringify(t) : t;
		});
	}
	t.exports = r;
})), Sr = /* @__PURE__ */ s(((e, t) => {
	var n = pr(), r = mr();
	function i(e) {
		return function(t) {
			var i = n.prototype.isPrototypeOf(t) ? t : r(t), a = Object.create(e);
			return a.client = i, a;
		};
	}
	t.exports = i;
})), Cr = /* @__PURE__ */ s(((e, t) => {
	var n = $(), r = vr(), i = yr(), a = xr(), o = Sr(), s = {}, c = [
		"country",
		"region",
		"postcode",
		"district",
		"place",
		"locality",
		"neighborhood",
		"address",
		"poi",
		"poi.landmark"
	];
	s.forwardGeocode = function(e) {
		r.assertShape({
			query: r.required(r.string),
			mode: r.oneOf("mapbox.places", "mapbox.places-permanent"),
			countries: r.arrayOf(r.string),
			proximity: r.oneOf(r.coordinates, "ip"),
			types: r.arrayOf(r.oneOf(c)),
			autocomplete: r.boolean,
			bbox: r.arrayOf(r.number),
			limit: r.number,
			language: r.arrayOf(r.string),
			routing: r.boolean,
			fuzzyMatch: r.boolean,
			worldview: r.string,
			session_token: r.string
		})(e), e.mode = e.mode || "mapbox.places";
		var t = a(n({ country: e.countries }, i(e, [
			"proximity",
			"types",
			"autocomplete",
			"bbox",
			"limit",
			"language",
			"routing",
			"fuzzyMatch",
			"worldview",
			"session_token"
		])));
		return this.client.createRequest({
			method: "GET",
			path: "/geocoding/v5/:mode/:query.json",
			params: i(e, ["mode", "query"]),
			query: t
		});
	}, s.reverseGeocode = function(e) {
		r.assertShape({
			query: r.required(r.coordinates),
			mode: r.oneOf("mapbox.places", "mapbox.places-permanent"),
			countries: r.arrayOf(r.string),
			types: r.arrayOf(r.oneOf(c)),
			bbox: r.arrayOf(r.number),
			limit: r.number,
			language: r.arrayOf(r.string),
			reverseMode: r.oneOf("distance", "score"),
			routing: r.boolean,
			worldview: r.string,
			session_token: r.string
		})(e), e.mode = e.mode || "mapbox.places";
		var t = a(n({ country: e.countries }, i(e, [
			"country",
			"types",
			"bbox",
			"limit",
			"language",
			"reverseMode",
			"routing",
			"worldview",
			"session_token"
		])));
		return this.client.createRequest({
			method: "GET",
			path: "/geocoding/v5/:mode/:query.json",
			params: i(e, ["mode", "query"]),
			query: t
		});
	}, t.exports = o(s);
})), wr, Tr = o((() => {
	wr = "useandom-26T198340PX75pxJACKVERYMINDBUSHWOLF_GQZbfghjklqvwyzrict";
})), Er = /* @__PURE__ */ c({
	customAlphabet: () => kr,
	customRandom: () => Or,
	nanoid: () => Ar,
	random: () => Dr,
	urlAlphabet: () => wr
}), Dr, Or, kr, Ar, jr = o((() => {
	Tr(), Dr = (e) => crypto.getRandomValues(new Uint8Array(e)), Or = (e, t, n) => {
		let r = (2 << Math.log(e.length - 1) / Math.LN2) - 1, i = -~(1.6 * r * t / e.length);
		return (a = t) => {
			if (a <= 0) return "";
			let o = "";
			for (;;) {
				let t = n(i), s = i | 0;
				for (; s--;) if (o += e[t[s] & r] || "", o.length === a) return o;
			}
		};
	}, kr = (e, t = 21) => Or(e, t, Dr), Ar = (e = 21) => crypto.getRandomValues(new Uint8Array(e)).reduce((e, t) => (t &= 63, e += t < 36 ? t.toString(36) : t < 62 ? (t - 26).toString(36).toUpperCase() : t > 62 ? "-" : "_", e), "");
})), Mr = /* @__PURE__ */ s(((e, t) => {
	var n = (jr(), d(Er)).nanoid;
	function r(e) {
		this.origin = e.origin || "https://api.mapbox.com", this.endpoint = "events/v2", this.access_token = e.accessToken, this.version = "0.3.0", this.pluginSessionID = this.generateSessionID(), this.sessionIncrementer = 0, this.userAgent = this.getUserAgent(), this.options = e, this.send = this.send.bind(this), this.countries = e.countries ? e.countries.split(",") : null, this.types = e.types ? e.types.split(",") : null, this.bbox = e.bbox ? e.bbox : null, this.language = e.language ? e.language.split(",") : null, this.limit = e.limit ? +e.limit : null, this.locale = navigator.language || null, this.enableEventLogging = this.shouldEnableLogging(e), this.eventQueue = [], this.flushInterval = e.flushInterval || 1e3, this.maxQueueSize = e.maxQueueSize || 100, this.timer = this.flushInterval ? setTimeout(this.flush.bind(this), this.flushInterval) : null, this.lastSentInput = "", this.lastSentIndex = 0;
	}
	r.prototype = {
		select: function(e, t) {
			var n = this.getEventPayload("search.select", t, { selectedFeature: e });
			if (n && !(n.resultIndex === this.lastSentIndex && n.queryString === this.lastSentInput || n.resultIndex == -1)) return this.lastSentIndex = n.resultIndex, this.lastSentInput = n.queryString, this.push(n);
		},
		start: function(e) {
			var t = this.getEventPayload("search.start", e);
			if (t) return this.push(t);
		},
		keyevent: function(e, t) {
			if (e.key && !(e.metaKey || [
				9,
				27,
				37,
				39,
				13,
				38,
				40
			].indexOf(e.keyCode) !== -1)) {
				var n = this.getEventPayload("search.keystroke", t, { key: e.key });
				if (n) return this.push(n);
			}
		},
		send: function(e, t) {
			if (!this.enableEventLogging) return t ? t() : void 0;
			var n = this.getRequestOptions(e);
			this.request(n, function(e) {
				if (e) return this.handleError(e, t);
				if (t) return t();
			}.bind(this));
		},
		getRequestOptions: function(e) {
			return Array.isArray(e) || (e = [e]), {
				method: "POST",
				host: this.origin,
				path: this.endpoint + "?access_token=" + this.access_token,
				headers: { "Content-Type": "application/json" },
				body: JSON.stringify(e)
			};
		},
		getEventPayload: function(e, t, n = {}) {
			if (e === "search.select" && !n.selectedFeature || e === "search.keystroke" && !n.key) return null;
			var r;
			if (!t.options.proximity) r = null;
			else if (typeof t.options.proximity == "object") r = [t.options.proximity.longitude, t.options.proximity.latitude];
			else if (t.options.proximity === "ip") {
				var i = t._headers ? t._headers["ip-proximity"] : null;
				r = i && typeof i == "string" ? i.split(",").map(parseFloat) : [999, 999];
			} else r = t.options.proximity;
			var a = t._map ? t._map.getZoom() : void 0, o = {
				event: e,
				version: this.getEventSchemaVersion(e),
				created: +/* @__PURE__ */ new Date(),
				sessionIdentifier: this.getSessionId(),
				country: this.countries,
				userAgent: this.userAgent,
				language: this.language,
				bbox: this.bbox,
				types: this.types,
				endpoint: "mapbox.places",
				autocomplete: t.options.autocomplete,
				fuzzyMatch: t.options.fuzzyMatch,
				proximity: r,
				limit: t.options.limit,
				routing: t.options.routing,
				worldview: t.options.worldview,
				mapZoom: a,
				keyboardLocale: this.locale
			};
			if (o.queryString = e === "search.select" ? t.inputString : e != "search.select" && t._inputEl ? t._inputEl.value : t.inputString, ["search.keystroke", "search.select"].includes(e) && (o.path = "geocoding/v5/mapbox.places"), e === "search.keystroke" && n.key) o.lastAction = n.key;
			else if (e === "search.select" && n.selectedFeature) {
				var s = n.selectedFeature;
				if (o.resultIndex = this.getSelectedIndex(s, t), o.resultPlaceName = s.place_name, o.resultId = s.id, s.properties && (o.resultMapboxId = s.properties.mapbox_id), t._typeahead) {
					var c = t._typeahead.data;
					c && c.length > 0 && (o.suggestionIds = this.getSuggestionIds(c), o.suggestionNames = this.getSuggestionNames(c), o.suggestionTypes = this.getSuggestionTypes(c), o.suggestionSources = this.getSuggestionSources(c));
				}
			}
			return this.validatePayload(o) ? o : null;
		},
		request: function(e, t) {
			var n = new XMLHttpRequest();
			for (var r in n.onreadystatechange = function() {
				if (this.readyState == 4) return this.status == 204 ? t(null) : t(this.statusText);
			}, n.open(e.method, e.host + "/" + e.path, !0), e.headers) {
				var i = e.headers[r];
				n.setRequestHeader(r, i);
			}
			n.send(e.body);
		},
		handleError: function(e, t) {
			if (t) return t(e);
		},
		generateSessionID: function() {
			return n();
		},
		getSessionId: function() {
			return this.pluginSessionID + "." + this.sessionIncrementer;
		},
		getUserAgent: function() {
			return "mapbox-gl-geocoder." + this.version + "." + navigator.userAgent;
		},
		getSelectedIndex: function(e, t) {
			if (t._typeahead) {
				var n = t._typeahead.data, r = e.id;
				return n.map(function(e) {
					return e.id;
				}).indexOf(r);
			}
		},
		getSuggestionIds: function(e) {
			return e.map(function(e) {
				return e.properties ? e.properties.mapbox_id || "" : e.id || "";
			});
		},
		getSuggestionNames: function(e) {
			return e.map(function(e) {
				return e.place_name || "";
			});
		},
		getSuggestionTypes: function(e) {
			return e.map(function(e) {
				return e.place_type && Array.isArray(e.place_type) && e.place_type[0] || "";
			});
		},
		getSuggestionSources: function(e) {
			return e.map(function(e) {
				return e._source || "";
			});
		},
		getEventSchemaVersion: function(e) {
			return ["search.keystroke", "search.select"].includes(e) ? "2.2" : "2.0";
		},
		validatePayload: function(e) {
			if (!e || !e.event) return !1;
			var t = [
				"event",
				"created",
				"sessionIdentifier",
				"queryString"
			], n = [
				"event",
				"created",
				"sessionIdentifier",
				"queryString",
				"lastAction"
			], r = [
				"event",
				"created",
				"sessionIdentifier",
				"queryString",
				"resultIndex",
				"path",
				"suggestionIds"
			], i = e.event;
			return i === "search.start" ? this.objectHasRequiredProps(e, t) : i === "search.keystroke" ? this.objectHasRequiredProps(e, n) : i !== "search.select" || this.objectHasRequiredProps(e, r);
		},
		objectHasRequiredProps: function(e, t) {
			return t.every(function(t) {
				return t === "queryString" ? typeof e[t] == "string" && e[t].length > 0 : e[t] !== void 0;
			});
		},
		shouldEnableLogging: function(e) {
			return !(e.enableEventLogging === !1 || e.origin && e.origin !== "https://api.mapbox.com");
		},
		flush: function() {
			this.eventQueue.length > 0 && (this.send(this.eventQueue), this.eventQueue = []), this.timer && clearTimeout(this.timer), this.flushInterval && (this.timer = setTimeout(this.flush.bind(this), this.flushInterval));
		},
		push: function(e, t) {
			this.eventQueue.push(e), (this.eventQueue.length >= this.maxQueueSize || t) && this.flush();
		},
		remove: function() {
			this.flush();
		}
	}, t.exports = r;
})), Nr = /* @__PURE__ */ s(((e, t) => {
	t.exports = { placeholder: {
		de: "Suche",
		it: "Ricerca",
		en: "Search",
		nl: "Zoeken",
		fr: "Chercher",
		ca: "Cerca",
		he: "לחפש",
		ja: "サーチ",
		lv: "Meklēt",
		pt: "Procurar",
		sr: "Претрага",
		zh: "搜索",
		cs: "Vyhledávání",
		hu: "Keresés",
		ka: "ძიება",
		nb: "Søke",
		sk: "Vyhľadávanie",
		th: "ค้นหา",
		fi: "Hae",
		is: "Leita",
		ko: "수색",
		pl: "Szukaj",
		sl: "Iskanje",
		fa: "جستجو",
		ru: "Поиск"
	} };
})), Pr = /* @__PURE__ */ s(((e, t, n) => {
	(function(e, n, r) {
		t !== void 0 && t.exports ? t.exports = r() : e[n] = r();
	})(n, "subtag", function() {
		var e = /^([a-zA-Z]{2,3})(?:[_-]+([a-zA-Z]{3})(?=$|[_-]+))?(?:[_-]+([a-zA-Z]{4})(?=$|[_-]+))?(?:[_-]+([a-zA-Z]{2}|[0-9]{3})(?=$|[_-]+))?/;
		function t(t) {
			return t.match(e) || [];
		}
		function n(e) {
			return t(e).filter(function(e, t) {
				return e && t;
			});
		}
		function r(e) {
			return e = t(e), {
				language: e[1] || "",
				extlang: e[2] || "",
				script: e[3] || "",
				region: e[4] || ""
			};
		}
		function i(e, t, n) {
			Object.defineProperty(e, t, {
				value: n,
				enumerable: !0
			});
		}
		function a(e, n, a) {
			function o(n) {
				return t(n)[e] || "";
			}
			i(o, "pattern", n), i(r, a, o);
		}
		return a(1, /^[a-zA-Z]{2,3}$/, "language"), a(2, /^[a-zA-Z]{3}$/, "extlang"), a(3, /^[a-zA-Z]{4}$/, "script"), a(4, /^[a-zA-Z]{2}$|^[0-9]{3}$/, "region"), i(r, "split", n), r;
	});
})), Fr = /* @__PURE__ */ s(((e, t) => {
	function n() {}
	n.prototype = {
		isSupport: function() {
			return !!window.navigator.geolocation;
		},
		getCurrentPosition: function() {
			let e = { enableHighAccuracy: !0 };
			return new Promise(function(t, n) {
				window.navigator.geolocation.getCurrentPosition(t, n, e);
			});
		}
	}, t.exports = n;
})), Ir = /* @__PURE__ */ s(((e, t) => {
	function n(e, t) {
		let n = r(e), i = [
			"address",
			"street",
			"place",
			"country"
		];
		var a;
		if (typeof t == "function") return t(n);
		let o = i.indexOf(t);
		return a = o === -1 ? i : i.slice(o), a.reduce(function(e, t) {
			return n[t] ? (e !== "" && (e += ", "), e + n[t]) : e;
		}, "");
	}
	function r(e) {
		let t = e.address || "", n = e.text || "", r = e.place_name || "", i = {
			address: r.split(",")[0],
			houseNumber: t,
			street: n,
			placeName: r
		};
		return e.context.forEach(function(e) {
			let t = e.id.split(".")[0];
			i[t] = e.text;
		}), i;
	}
	t.exports = {
		transformFeatureToGeolocationText: n,
		getAddressInfo: r,
		REVERSE_GEOCODE_COORD_RGX: /^[ ]*(-?\d{1,3}(\.\d{0,256})?)[, ]+(-?\d{1,3}(\.\d{0,256})?)[ ]*$/
	};
})), Lr = /* @__PURE__ */ s(((e, t) => {
	var n = Qn(), r = $n(), i = $(), a = er().EventEmitter, o = tr(), s = hr(), c = Cr(), l = Mr(), u = Nr(), d = Pr(), f = Fr(), p = Ir(), m = {
		FORWARD: 0,
		LOCAL: 1,
		REVERSE: 2
	};
	function h() {
		var e = document.createElement("div");
		return e.className = "mapboxgl-ctrl-geocoder--powered-by", e.innerHTML = "<a href=\"https://www.mapbox.com/search-service\" target=\"_blank\">Powered by Mapbox</a>", e;
	}
	function g(e) {
		this._eventEmitter = new a(), this.options = i({}, this.options, e), this.inputString = "", this.fresh = !0, this.lastSelected = null, this.geolocation = new f();
	}
	function _(e) {
		return e ? String(e).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#39;") : "";
	}
	g.prototype = {
		options: {
			zoom: 16,
			flyTo: !0,
			trackProximity: !0,
			minLength: 2,
			reverseGeocode: !1,
			flipCoordinates: !1,
			limit: 5,
			origin: "https://api.mapbox.com",
			enableEventLogging: !0,
			marker: !0,
			mapboxgl: null,
			collapsed: !1,
			clearAndBlurOnEsc: !1,
			clearOnBlur: !1,
			enableGeolocation: !1,
			addressAccuracy: "street",
			useBrowserFocus: !1,
			getItemValue: function(e) {
				return e.place_name;
			},
			render: function(e) {
				var t = _(e.place_name).split(",");
				return "<div class=\"mapboxgl-ctrl-geocoder--suggestion\"><div class=\"mapboxgl-ctrl-geocoder--suggestion-title\">" + t[0] + "</div><div class=\"mapboxgl-ctrl-geocoder--suggestion-address\">" + t.splice(1, t.length).join(",") + "</div></div>";
			}
		},
		_headers: {},
		addTo: function(e) {
			function t(e, t) {
				if (!document.body.contains(t)) throw Error("Element provided to #addTo() exists, but is not in the DOM");
				let n = e.onAdd();
				t.appendChild(n);
			}
			if (e._controlContainer) e.addControl(this);
			else if (e instanceof HTMLElement) t(this, e);
			else if (typeof e == "string") {
				let n = document.querySelectorAll(e);
				if (n.length === 0) throw Error("Element ", e, "not found.");
				if (n.length > 1) throw Error("Geocoder can only be added to a single html element");
				t(this, n[0]);
			} else throw Error("Error: addTo must be a mapbox-gl-js map, an html element, or a CSS selector query for a single html element");
		},
		onAdd: function(e) {
			if (e && typeof e != "string" && (this._map = e), this.setLanguage(), this.options.localGeocoderOnly || (this.geocoderService = c(s({
				accessToken: this.options.accessToken,
				origin: this.options.origin
			}))), this.options.localGeocoderOnly && !this.options.localGeocoder) throw Error("A localGeocoder function must be specified to use localGeocoderOnly mode");
			this.eventManager = new l(this.options), this._onChange = this._onChange.bind(this), this._onKeyDown = this._onKeyDown.bind(this), this._onPaste = this._onPaste.bind(this), this._onBlur = this._onBlur.bind(this), this._showButton = this._showButton.bind(this), this._hideButton = this._hideButton.bind(this), this._onQueryResult = this._onQueryResult.bind(this), this.clear = this.clear.bind(this), this._updateProximity = this._updateProximity.bind(this), this._collapse = this._collapse.bind(this), this._unCollapse = this._unCollapse.bind(this), this._clear = this._clear.bind(this), this._clearOnBlur = this._clearOnBlur.bind(this), this._geolocateUser = this._geolocateUser.bind(this), this._onSuggestionItemFocus = this._onSuggestionItemFocus.bind(this), this._onSuggestionItemKeyDown = this._onSuggestionItemKeydown.bind(this);
			var t = this.container = document.createElement("div");
			t.className = "mapboxgl-ctrl-geocoder mapboxgl-ctrl";
			var i = this.createIcon("search", "<path d=\"M7.4 2.5c-2.7 0-4.9 2.2-4.9 4.9s2.2 4.9 4.9 4.9c1 0 1.8-.2 2.5-.8l3.7 3.7c.2.2.4.3.8.3.7 0 1.1-.4 1.1-1.1 0-.3-.1-.5-.3-.8L11.4 10c.4-.8.8-1.6.8-2.5.1-2.8-2.1-5-4.8-5zm0 1.6c1.8 0 3.2 1.4 3.2 3.2s-1.4 3.2-3.2 3.2-3.3-1.3-3.3-3.1 1.4-3.3 3.3-3.3z\"/>");
			this._inputEl = document.createElement("input"), this._inputEl.type = "text", this._inputEl.className = "mapboxgl-ctrl-geocoder--input", this.setPlaceholder(), this.options.collapsed && (this._collapse(), this.container.addEventListener("mouseenter", this._unCollapse), this.container.addEventListener("mouseleave", this._collapse), this._inputEl.addEventListener("focus", this._unCollapse)), (this.options.collapsed || this.options.clearOnBlur) && this._inputEl.addEventListener("blur", this._onBlur), this._inputEl.addEventListener("keydown", r(this._onKeyDown, 200)), this._inputEl.addEventListener("paste", this._onPaste), this._inputEl.addEventListener("change", this._onChange), this.container.addEventListener("mouseenter", this._showButton), this.container.addEventListener("mouseleave", this._hideButton), this._inputEl.addEventListener("keyup", function(e) {
				this.eventManager.keyevent(e, this);
			}.bind(this));
			var a = document.createElement("div");
			a.classList.add("mapboxgl-ctrl-geocoder--pin-right"), this._clearEl = document.createElement("button"), this._clearEl.setAttribute("aria-label", "Clear"), this._clearEl.addEventListener("click", this.clear), this._clearEl.className = "mapboxgl-ctrl-geocoder--button";
			var o = this.createIcon("close", "<path d=\"M3.8 2.5c-.6 0-1.3.7-1.3 1.3 0 .3.2.7.5.8L7.2 9 3 13.2c-.3.3-.5.7-.5 1 0 .6.7 1.3 1.3 1.3.3 0 .7-.2 1-.5L9 10.8l4.2 4.2c.2.3.7.3 1 .3.6 0 1.3-.7 1.3-1.3 0-.3-.2-.7-.3-1l-4.4-4L15 4.6c.3-.2.5-.5.5-.8 0-.7-.7-1.3-1.3-1.3-.3 0-.7.2-1 .3L9 7.1 4.8 2.8c-.3-.1-.7-.3-1-.3z\"/>");
			if (this._clearEl.appendChild(o), this._loadingEl = this.createIcon("loading", "<path fill=\"#333\" d=\"M4.4 4.4l.8.8c2.1-2.1 5.5-2.1 7.6 0l.8-.8c-2.5-2.5-6.7-2.5-9.2 0z\"/><path opacity=\".1\" d=\"M12.8 12.9c-2.1 2.1-5.5 2.1-7.6 0-2.1-2.1-2.1-5.5 0-7.7l-.8-.8c-2.5 2.5-2.5 6.7 0 9.2s6.6 2.5 9.2 0 2.5-6.6 0-9.2l-.8.8c2.2 2.1 2.2 5.6 0 7.7z\"/>"), a.appendChild(this._clearEl), a.appendChild(this._loadingEl), t.appendChild(i), t.appendChild(this._inputEl), this.options.enableGeolocation && this.geolocation.isSupport()) {
				this._geolocateEl = document.createElement("button"), this._geolocateEl.setAttribute("aria-label", "Geolocate"), this._geolocateEl.addEventListener("click", this._geolocateUser), this._geolocateEl.className = "mapboxgl-ctrl-geocoder--button";
				var u = this.createIcon("geolocate", "<path d=\"M12.999 3.677L2.042 8.269c-.962.403-.747 1.823.29 1.912l5.032.431.431 5.033c.089 1.037 1.509 1.252 1.912.29l4.592-10.957c.345-.822-.477-1.644-1.299-1.299z\" fill=\"#4264fb\"/>");
				this._geolocateEl.appendChild(u), a.appendChild(this._geolocateEl), this._showGeolocateButton();
			}
			var d = this._typeahead = new n(this._inputEl, [], {
				hideOnBlur: !this.options.useBrowserFocus,
				filter: !1,
				minLength: this.options.minLength,
				limit: this.options.limit
			});
			t.insertBefore(a, d.list.wrapper), this.setRenderFunction(this.options.render), d.getItemValue = this.options.getItemValue;
			let f = this._typeahead.handleKeyDown.bind(this._typeahead), p = this._typeahead.handleKeyUp.bind(this._typeahead);
			this._typeahead.handleKeyUp, this.options.useBrowserFocus && (this._typeahead.handleKeyDown = function(e) {
				(e.keyCode !== 9 || d.list.isEmpty()) && (e.keyCode === 40 ? (this._typeahead.list.active = 0, this._typeahead.list.element.querySelectorAll("li").forEach(function(e) {
					e.classList.remove("active");
				}), this._typeahead.list.element.querySelectorAll("li")[0].classList.add("active"), this._typeahead.list.element.querySelectorAll("li")[0].focus()) : e.keyCode === 38 ? (this._typeahead.list.active = d.list.items.length - 1, this._typeahead.list.element.querySelectorAll("li").forEach(function(e) {
					e.classList.remove("active");
				}), this._typeahead.list.element.querySelectorAll("li")[this._typeahead.list.active].classList.add("active"), this._typeahead.list.element.querySelectorAll("li")[this._typeahead.list.active].focus()) : f(e));
			}.bind(this), this._typeahead.handleKeyUp = function(e) {
				e && e.keyCode === 16 ? e.preventDefault() : p(e);
			});
			var m = d.list.draw, g = this._footerNode = h(), _ = this;
			return d.list.draw = function() {
				_.options.useBrowserFocus && d.list.element.querySelectorAll("li").forEach(function(e) {
					e.removeEventListener("focus", _._onSuggestionItemFocus), e.removeEventListener("keydown", _._onSuggestionItemKeyDown);
				}), m.call(this), _.options.useBrowserFocus && d.list.element.querySelectorAll("li").forEach(function(e, t) {
					t === 0 && e.focus(), e.setAttribute("data-index", t), e.tabIndex = 0, e.addEventListener("focus", this._onSuggestionItemFocus), e.addEventListener("keydown", this._onSuggestionItemKeyDown);
				}.bind(_)), g.addEventListener("mousedown", function() {
					this.selectingListItem = !0;
				}.bind(this)), g.addEventListener("mouseup", function() {
					this.selectingListItem = !1;
				}.bind(this)), this.element.appendChild(g);
			}, this.mapMarker = null, this._handleMarker = this._handleMarker.bind(this), this._map && (this.options.trackProximity && (this._updateProximity(), this._map.on("moveend", this._updateProximity)), this._mapboxgl = this.options.mapboxgl, !this._mapboxgl && this.options.marker && (console.error("No mapboxgl detected in options. Map markers are disabled. Please set options.mapboxgl."), this.options.marker = !1)), t;
		},
		_onSuggestionItemKeydown(e) {
			let t = e.keyCode;
			if (t !== 9) {
				if (t === 13) {
					e.preventDefault();
					let t = this._typeahead.list.element.querySelector(".active");
					if (t) {
						let e = t.getAttribute("data-index"), n = this._typeahead.list.items[e];
						n && (this._typeahead.value(n.original), this._typeahead.list.hide());
					}
				} else if (t === 38 || t === 40) {
					let n = this._typeahead.list.element.querySelectorAll("li");
					if (n.length > 0) {
						if (t === 38) {
							if (this._typeahead.list.active > 0) e.preventDefault(), this._typeahead.list.active--;
							else {
								this._typeahead.el.focus();
								return;
							}
						} else if (t === 40) {
							if (e.preventDefault(), this._typeahead.list.active < n.length - 1) this._typeahead.list.active++;
							else return;
						}
						n.forEach(function(e) {
							e.classList.remove("active");
						});
						let r = n[this._typeahead.list.active];
						r && (r.classList.add("active"), r.focus());
					}
				}
			}
		},
		_onSuggestionItemFocus(e) {
			this._typeahead.list.active = e.target.getAttribute("data-index"), this._typeahead.list.element.querySelectorAll("li").forEach(function(e) {
				e.classList.remove("active");
			}), e.target.classList.add("active");
		},
		_geolocateUser: function() {
			this._hideGeolocateButton(), this._showLoadingIcon(), this.geolocation.getCurrentPosition().then(function(e) {
				this._hideLoadingIcon();
				let t = { geometry: {
					type: "Point",
					coordinates: [e.coords.longitude, e.coords.latitude]
				} };
				this._handleMarker(t), this._fly(t), this._typeahead.clear(), this._typeahead.selected = !0, this.lastSelected = JSON.stringify(t), this._showClearButton(), this.fresh = !1;
				let n = {
					limit: 1,
					language: [this.options.language],
					query: t.geometry.coordinates,
					types: ["address"]
				};
				if (this.options.localGeocoderOnly) {
					let e = t.geometry.coordinates[0] + "," + t.geometry.coordinates[1];
					this._setInputValue(e), this._eventEmitter.emit("result", { result: t });
				} else this.geocoderService.reverseGeocode(n).send().then(function(e) {
					let n = e.body.features[0];
					if (n) {
						let e = p.transformFeatureToGeolocationText(n, this.options.addressAccuracy);
						this._setInputValue(e), n.user_coordinates = t.geometry.coordinates, this._eventEmitter.emit("result", { result: n });
					} else this._eventEmitter.emit("result", { result: { user_coordinates: t.geometry.coordinates } });
				}.bind(this));
			}.bind(this)).catch(function(e) {
				e.code === 1 ? this._renderUserDeniedGeolocationError() : this._renderLocationError(), this._hideLoadingIcon(), this._showGeolocateButton(), this._hideAttribution();
			}.bind(this));
		},
		createIcon: function(e, t) {
			var n = document.createElementNS("http://www.w3.org/2000/svg", "svg");
			return n.setAttribute("class", "mapboxgl-ctrl-geocoder--icon mapboxgl-ctrl-geocoder--icon-" + e), n.setAttribute("viewBox", "0 0 18 18"), n.setAttribute("xml:space", "preserve"), n.setAttribute("width", 18), n.setAttribute("height", 18), n.innerHTML = t, n;
		},
		onRemove: function() {
			return this.container.parentNode.removeChild(this.container), this.options.trackProximity && this._map && this._map.off("moveend", this._updateProximity), this._removeMarker(), this._map = null, this;
		},
		_setInputValue: function(e) {
			this._inputEl.value = e, setTimeout(function() {
				this._inputEl.focus(), this._inputEl.scrollLeft = 0, this._inputEl.setSelectionRange(0, 0);
			}.bind(this), 1);
		},
		_onPaste: function(e) {
			var t = (e.clipboardData || window.clipboardData).getData("text");
			t.length >= this.options.minLength && this._geocode(t);
		},
		_onKeyDown: function(e) {
			var t = 27, n = 9;
			if (e.keyCode === t && this.options.clearAndBlurOnEsc) return this._clear(e), this._inputEl.blur();
			var r = e.target && e.target.shadowRoot ? e.target.shadowRoot.activeElement : e.target;
			if (!(r && r.value)) return this.fresh = !0, e.keyCode !== n && this.clear(e), this._showGeolocateButton(), this._hideClearButton();
			this._hideGeolocateButton(), e.metaKey || [
				n,
				t,
				37,
				39,
				13,
				38,
				40
			].indexOf(e.keyCode) !== -1 || r.value.length >= this.options.minLength && this._geocode(r.value);
		},
		_showButton: function() {
			this._typeahead.selected && this._showClearButton();
		},
		_hideButton: function() {
			this._typeahead.selected && this._hideClearButton();
		},
		_showClearButton: function() {
			this._clearEl.style.display = "block";
		},
		_hideClearButton: function() {
			this._clearEl.style.display = "none";
		},
		_showGeolocateButton: function() {
			this._geolocateEl && this.geolocation.isSupport() && (this._geolocateEl.style.display = "block");
		},
		_hideGeolocateButton: function() {
			this._geolocateEl && (this._geolocateEl.style.display = "none");
		},
		_showLoadingIcon: function() {
			this._loadingEl.style.display = "block";
		},
		_hideLoadingIcon: function() {
			this._loadingEl.style.display = "none";
		},
		_showAttribution: function() {
			this._footerNode.style.display = "block";
		},
		_hideAttribution: function() {
			this._footerNode.style.display = "none";
		},
		_onBlur: function(e) {
			this.options.clearOnBlur && this._clearOnBlur(e), this.options.collapsed && this._collapse();
		},
		_onChange: function() {
			var e = this._typeahead.selected;
			e && JSON.stringify(e) !== this.lastSelected && (this._hideClearButton(), this.options.flyTo && this._fly(e), this.options.marker && this._mapboxgl && this._handleMarker(e), this._inputEl.focus(), this._inputEl.scrollLeft = 0, this._inputEl.setSelectionRange(0, 0), this.lastSelected = JSON.stringify(e), this._eventEmitter.emit("result", { result: e }), this.eventManager.select(e, this));
		},
		_fly: function(e) {
			var t;
			if (e.properties && o[e.properties.short_code]) t = i({}, this.options.flyTo), this._map && this._map.fitBounds(o[e.properties.short_code].bbox, t);
			else if (e.bbox) {
				var n = e.bbox;
				t = i({}, this.options.flyTo), this._map && this._map.fitBounds([[n[0], n[1]], [n[2], n[3]]], t);
			} else t = i({}, { zoom: this.options.zoom }, this.options.flyTo), e.center ? t.center = e.center : e.geometry && e.geometry.type && e.geometry.type === "Point" && e.geometry.coordinates && (t.center = e.geometry.coordinates), this._map && this._map.flyTo(t);
		},
		_requestType: function(e, t) {
			return e.localGeocoderOnly ? m.LOCAL : e.reverseGeocode && p.REVERSE_GEOCODE_COORD_RGX.test(t) ? m.REVERSE : m.FORWARD;
		},
		_setupConfig: function(e, t) {
			let n = [
				"bbox",
				"limit",
				"proximity",
				"countries",
				"types",
				"language",
				"reverseMode",
				"mode",
				"autocomplete",
				"fuzzyMatch",
				"routing",
				"worldview"
			], r = /[\s,]+/;
			var a = this, o = n.reduce(function(e, t) {
				if (a.options[t] === void 0 || a.options[t] === null) return e;
				e[t] = [
					"countries",
					"types",
					"language"
				].indexOf(t) > -1 ? a.options[t].split(r) : a.options[t];
				let n = typeof a.options[t].longitude == "number" && typeof a.options[t].latitude == "number";
				return t === "proximity" && n && (e[t] = [a.options[t].longitude, a.options[t].latitude]), e;
			}, {});
			switch (e) {
				case m.REVERSE:
					var s = t.split(r).map(function(e) {
						return parseFloat(e, 10);
					});
					a.options.flipCoordinates || s.reverse(), o.types && o.types[0], o = i(o, {
						query: s,
						limit: 1
					}), [
						"proximity",
						"autocomplete",
						"fuzzyMatch",
						"bbox"
					].forEach(function(e) {
						e in o && delete o[e];
					});
					break;
				case m.FORWARD: {
					let e = t.trim();
					/^(-?\d{1,3}(\.\d{0,256})?)[, ]+(-?\d{1,3}(\.\d{0,256})?)?$/.test(e) && (t = t.replace(/,/g, " ")), o = i(o, { query: t });
				}
			}
			return o.session_token = this.eventManager.getSessionId(), o;
		},
		_geocode: function(e) {
			this.inputString = e, this._showLoadingIcon(), this._eventEmitter.emit("loading", { query: e });
			let t = this._requestType(this.options, e), n = this._setupConfig(t, e);
			var r;
			switch (t) {
				case m.LOCAL:
					r = Promise.resolve();
					break;
				case m.FORWARD:
					r = this.geocoderService.forwardGeocode(n).send();
					break;
				case m.REVERSE: r = this.geocoderService.reverseGeocode(n).send();
			}
			var i = this.options.localGeocoder && this.options.localGeocoder(e) || [], a = [], o = null;
			return r.catch(function(e) {
				o = e;
			}.bind(this)).then(function(t) {
				this._hideLoadingIcon();
				var r = {};
				return t ? t.statusCode == "200" && (r = t.body, r.request = t.request, r.headers = t.headers, this._headers = t.headers) : r = {
					type: "FeatureCollection",
					features: []
				}, r.config = n, this.fresh &&= (this.eventManager.start(this), !1), r.features && r.features.length && r.features.map(function(e) {
					e._source = "mapbox";
				}), r.features = r.features ? i.concat(r.features) : i, this.options.externalGeocoder ? (a = this.options.externalGeocoder(e, r.features) || Promise.resolve([]), a.then(function(e) {
					return r.features = r.features ? e.concat(r.features) : e, r;
				}, function() {
					return r;
				})) : r;
			}.bind(this)).then(function(e) {
				if (o) throw o;
				this.options.filter && e.features.length && (e.features = e.features.filter(this.options.filter)), e.features.length ? (this._showClearButton(), this._hideGeolocateButton(), this._showAttribution(), this._eventEmitter.emit("results", e), this._typeahead.update(e.features)) : (this._hideClearButton(), this._hideAttribution(), this._typeahead.selected = null, this._renderNoResults(), this._eventEmitter.emit("results", e));
			}.bind(this)).catch(function(e) {
				this._hideLoadingIcon(), this._hideAttribution(), i.length && this.options.localGeocoder || a.length && this.options.externalGeocoder ? (this._showClearButton(), this._hideGeolocateButton(), this._typeahead.update(i)) : (this._hideClearButton(), this._typeahead.selected = null, this._renderError()), this._eventEmitter.emit("results", { features: i }), this._eventEmitter.emit("error", { error: e });
			}.bind(this)), r;
		},
		_clear: function(e) {
			e && e.preventDefault(), this._inputEl.value = "", this._typeahead.selected = null, this._typeahead.clear(), this.eventManager.sessionIncrementer++, this._onChange(), this._hideClearButton(), this._showGeolocateButton(), this._removeMarker(), this.lastSelected = null, this._eventEmitter.emit("clear"), this.fresh = !0;
		},
		clear: function(e) {
			this._clear(e), this._inputEl.focus(), this.options.useBrowserFocus && this._typeahead.list.hide();
		},
		_clearOnBlur: function(e) {
			var t = this;
			e.relatedTarget && t._clear(e);
		},
		_onQueryResult: function(e) {
			var t = e.body;
			if (t.features.length) {
				var n = t.features[0];
				this._typeahead.selected = n, this._inputEl.value = n.place_name, this._onChange();
			}
		},
		_updateProximity: function() {
			if (this._map && this.options.trackProximity) {
				if (this._map.getZoom() > 9) {
					var e = this._map.getCenter().wrap();
					this.setProximity({
						longitude: e.lng,
						latitude: e.lat
					}, !1);
				} else this.setProximity(null, !1);
			}
		},
		_collapse: function() {
			!this._inputEl.value && this._inputEl !== document.activeElement && this.container.classList.add("mapboxgl-ctrl-geocoder--collapsed");
		},
		_unCollapse: function() {
			this.container.classList.remove("mapboxgl-ctrl-geocoder--collapsed");
		},
		query: function(e) {
			return this._geocode(e).then(this._onQueryResult), this;
		},
		_renderError: function() {
			this._renderMessage("<div class='mapbox-gl-geocoder--error'>There was an error reaching the server</div>");
		},
		_renderLocationError: function() {
			this._renderMessage("<div class='mapbox-gl-geocoder--error'>A location error has occurred</div>");
		},
		_renderNoResults: function() {
			this._renderMessage("<div class='mapbox-gl-geocoder--error mapbox-gl-geocoder--no-results'>No results found</div>");
		},
		_renderUserDeniedGeolocationError: function() {
			this._renderMessage("<div class='mapbox-gl-geocoder--error'>Geolocation permission denied</div>");
		},
		_renderMessage: function(e) {
			this._typeahead.update([]), this._typeahead.selected = null, this._typeahead.clear(), this._typeahead.renderError(e);
		},
		_getPlaceholderText: function() {
			if (this.options.placeholder) return this.options.placeholder;
			if (this.options.language) {
				var e = this.options.language.split(",")[0], t = d.language(e), n = u.placeholder[t];
				if (n) return n;
			}
			return "Search";
		},
		setInput: function(e, t) {
			return t === void 0 && (t = !1), this._inputEl.value = e, this._typeahead.selected = null, this._typeahead.clear(), e.length >= this.options.minLength && (t ? this._geocode(e) : this._onChange()), this;
		},
		setProximity: function(e, t = !0) {
			return this.options.proximity = e, t && (this.options.trackProximity = !1), this;
		},
		getProximity: function() {
			return this.options.proximity;
		},
		setRenderFunction: function(e) {
			return this._typeahead.render = typeof e == "function" ? e : g.prototype.options.render, this;
		},
		getRenderFunction: function() {
			return this._typeahead.render;
		},
		setLanguage: function(e) {
			var t = navigator.language || navigator.userLanguage || navigator.browserLanguage;
			return this.options.language = e || this.options.language || t, this;
		},
		getLanguage: function() {
			return this.options.language;
		},
		getZoom: function() {
			return this.options.zoom;
		},
		setZoom: function(e) {
			return this.options.zoom = e, this;
		},
		getFlyTo: function() {
			return this.options.flyTo;
		},
		setFlyTo: function(e) {
			return this.options.flyTo = e, this;
		},
		getPlaceholder: function() {
			return this.options.placeholder;
		},
		setPlaceholder: function(e) {
			return this.options.placeholder = e || this._getPlaceholderText(), this._inputEl.placeholder = this.options.placeholder, this._inputEl.setAttribute("aria-label", this.options.placeholder), this;
		},
		getBbox: function() {
			return this.options.bbox;
		},
		setBbox: function(e) {
			return this.options.bbox = e, this;
		},
		getCountries: function() {
			return this.options.countries;
		},
		setCountries: function(e) {
			return this.options.countries = e, this;
		},
		getTypes: function() {
			return this.options.types;
		},
		setTypes: function(e) {
			return this.options.types = e, this;
		},
		getMinLength: function() {
			return this.options.minLength;
		},
		setMinLength: function(e) {
			return this.options.minLength = e, this._typeahead && (this._typeahead.options.minLength = e), this;
		},
		getLimit: function() {
			return this.options.limit;
		},
		setLimit: function(e) {
			return this.options.limit = e, this._typeahead && (this._typeahead.options.limit = e), this;
		},
		getFilter: function() {
			return this.options.filter;
		},
		setFilter: function(e) {
			return this.options.filter = e, this;
		},
		setOrigin: function(e) {
			return this.options.origin = e, this.geocoderService = c(s({
				accessToken: this.options.accessToken,
				origin: this.options.origin
			})), this;
		},
		getOrigin: function() {
			return this.options.origin;
		},
		setAccessToken: function(e) {
			return this.options.accessToken = e, this.geocoderService = c(s({
				accessToken: this.options.accessToken,
				origin: this.options.origin
			})), this;
		},
		setAutocomplete: function(e) {
			return this.options.autocomplete = e, this;
		},
		getAutocomplete: function() {
			return this.options.autocomplete;
		},
		setFuzzyMatch: function(e) {
			return this.options.fuzzyMatch = e, this;
		},
		getFuzzyMatch: function() {
			return this.options.fuzzyMatch;
		},
		setRouting: function(e) {
			return this.options.routing = e, this;
		},
		getRouting: function() {
			return this.options.routing;
		},
		setWorldview: function(e) {
			return this.options.worldview = e, this;
		},
		getWorldview: function() {
			return this.options.worldview;
		},
		_handleMarker: function(e) {
			if (this._map) {
				this._removeMarker();
				var t = i({}, { color: "#4668F2" }, this.options.marker);
				return this.mapMarker = new this._mapboxgl.Marker(t), e.center ? this.mapMarker.setLngLat(e.center).addTo(this._map) : e.geometry && e.geometry.type && e.geometry.type === "Point" && e.geometry.coordinates && this.mapMarker.setLngLat(e.geometry.coordinates).addTo(this._map), this;
			}
		},
		_removeMarker: function() {
			this.mapMarker &&= (this.mapMarker.remove(), null);
		},
		on: function(e, t) {
			return this._eventEmitter.on(e, t), this;
		},
		off: function(e, t) {
			return this._eventEmitter.removeListener(e, t), this.eventManager.remove(), this;
		}
	}, t.exports = g;
})), Rr, zr;
function Br() {
	return zr ||= Promise.resolve().then(() => /* @__PURE__ */ u(Lr(), 1)).then((e) => (Rr = e.default, Rr)), zr;
}
function Vr() {
	throw Error("[@studiometa/ui-mapbox] mapbox-gl has not been resolved yet. Await resolveMapboxGl() (or call provideMapboxGl()) before reading it synchronously.");
}
// node_modules/@studiometa/ui-mapbox/dist/MapboxGeocoder.js
var Hr = class extends Jn {
	static config = {
		name: "MapboxGeocoder",
		mountStrategy: "visible",
		options: {
			accessToken: String,
			addToMap: Boolean,
			options: Object
		}
	};
	__control;
	get control() {
		return this.__control;
	}
	get target() {
		return this.$options.addToMap ? this.map : this.$el;
	}
	async mounted() {
		let e = !this.$options.addToMap && !this.$el.parentElement?.closest(At("MapboxMap"));
		if (e && !this.__controlOptions().accessToken) {
			this.$warn("mapbox-geocoder.missing-access-token", "A MapboxGeocoder without a parent MapboxMap needs an access token: set its `data-option-access-token`.");
			return;
		}
		let t = await Br();
		if (this.$isMounted) {
			if (e) try {
				this.__addControl(new t(this.__controlOptions()));
			} catch (e) {
				this.__handleError(e);
			}
			else this.whenMapReady(() => {
				this.__addControl(new t({
					...this.__controlOptions(this.__readyMapboxMap?.$options.accessToken),
					mapboxgl: Vr()
				}));
			});
		}
	}
	__controlOptions(e) {
		return {
			accessToken: this.$options.accessToken || e,
			...this.$options.options
		};
	}
	__addControl(e) {
		this.__control &&= (this.$options.addToMap || this.__control.onRemove(), void 0), e.on?.("result", (e) => this.$emit("map-result", { result: e.result })), e.addTo(this.target), this.__control = e;
	}
	__onDestroyed() {
		this.__control &&= (this.$options.addToMap ? this.__readyMap?.removeControl(this.__control) : this.__control.onRemove(), void 0);
	}
};
// assets/fill.js
function Ur(e) {
	let t = {}, [n, r] = Array.isArray(e?.center) ? e.center : [];
	Number.isFinite(r) && Number.isFinite(n) && (t.latitude = String(Math.round(r * 1e6) / 1e6), t.longitude = String(Math.round(n * 1e6) / 1e6)), (Array.isArray(e?.place_type) ? e.place_type : []).includes("address") && e.text && (t.street = [e.address, e.text].filter(Boolean).join(" "));
	let i = [e, ...Array.isArray(e?.context) ? e.context : []];
	for (let [e, n] of Object.entries({
		postcode: "postal_code",
		place: "city",
		country: "country"
	})) {
		let r = i.find((t) => typeof t?.id == "string" && t.id.startsWith(`${e}.`) && typeof t.text == "string");
		r && (t[n] = r.text);
	}
	return t;
}
function Wr(e, t) {
	for (let [n, r] of Object.entries(t)) {
		let t = e.querySelector(`[name="foehn_places[${n}]"]`);
		t && (t.value = r, t.dispatchEvent(new Event("input", { bubbles: !0 })), t.dispatchEvent(new Event("change", { bubbles: !0 })));
	}
}
document.addEventListener("map-result", (e) => {
	let t = e.target instanceof Element ? e.target.closest("[data-foehn-places-geocoder]") : null;
	t && Wr(t.closest(".postbox") ?? document, Ur(e.detail?.result));
}, !0), En(Hr);
