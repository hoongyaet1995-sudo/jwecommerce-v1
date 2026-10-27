function C0(s) {
  return s && s.__esModule && Object.prototype.hasOwnProperty.call(s, "default") ? s.default : s;
}
var qc = { exports: {} }, In = {};
/**
 * @license React
 * react-jsx-runtime.production.js
 *
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
var Vd;
function O0() {
  if (Vd) return In;
  Vd = 1;
  var s = Symbol.for("react.transitional.element"), d = Symbol.for("react.fragment");
  function b(f, E, z) {
    var O = null;
    if (z !== void 0 && (O = "" + z), E.key !== void 0 && (O = "" + E.key), "key" in E) {
      z = {};
      for (var U in E)
        U !== "key" && (z[U] = E[U]);
    } else z = E;
    return E = z.ref, {
      $$typeof: s,
      type: f,
      key: O,
      ref: E !== void 0 ? E : null,
      props: z
    };
  }
  return In.Fragment = d, In.jsx = b, In.jsxs = b, In;
}
var Kd;
function D0() {
  return Kd || (Kd = 1, qc.exports = O0()), qc.exports;
}
var n = D0(), Bc = { exports: {} }, be = {};
/**
 * @license React
 * react.production.js
 *
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
var Jd;
function R0() {
  if (Jd) return be;
  Jd = 1;
  var s = Symbol.for("react.transitional.element"), d = Symbol.for("react.portal"), b = Symbol.for("react.fragment"), f = Symbol.for("react.strict_mode"), E = Symbol.for("react.profiler"), z = Symbol.for("react.consumer"), O = Symbol.for("react.context"), U = Symbol.for("react.forward_ref"), M = Symbol.for("react.suspense"), j = Symbol.for("react.memo"), _ = Symbol.for("react.lazy"), y = Symbol.for("react.activity"), p = Symbol.iterator;
  function Y(m) {
    return m === null || typeof m != "object" ? null : (m = p && m[p] || m["@@iterator"], typeof m == "function" ? m : null);
  }
  var X = {
    isMounted: function() {
      return !1;
    },
    enqueueForceUpdate: function() {
    },
    enqueueReplaceState: function() {
    },
    enqueueSetState: function() {
    }
  }, I = Object.assign, ie = {};
  function J(m, o, D) {
    this.props = m, this.context = o, this.refs = ie, this.updater = D || X;
  }
  J.prototype.isReactComponent = {}, J.prototype.setState = function(m, o) {
    if (typeof m != "object" && typeof m != "function" && m != null)
      throw Error(
        "takes an object of state variables to update or a function which returns an object of state variables."
      );
    this.updater.enqueueSetState(this, m, o, "setState");
  }, J.prototype.forceUpdate = function(m) {
    this.updater.enqueueForceUpdate(this, m, "forceUpdate");
  };
  function ue() {
  }
  ue.prototype = J.prototype;
  function V(m, o, D) {
    this.props = m, this.context = o, this.refs = ie, this.updater = D || X;
  }
  var W = V.prototype = new ue();
  W.constructor = V, I(W, J.prototype), W.isPureReactComponent = !0;
  var te = Array.isArray;
  function le() {
  }
  var P = { H: null, A: null, T: null, S: null }, F = Object.prototype.hasOwnProperty;
  function ee(m, o, D) {
    var G = D.ref;
    return {
      $$typeof: s,
      type: m,
      key: o,
      ref: G !== void 0 ? G : null,
      props: D
    };
  }
  function fe(m, o) {
    return ee(m.type, o, m.props);
  }
  function H(m) {
    return typeof m == "object" && m !== null && m.$$typeof === s;
  }
  function h(m) {
    var o = { "=": "=0", ":": "=2" };
    return "$" + m.replace(/[=:]/g, function(D) {
      return o[D];
    });
  }
  var x = /\/+/g;
  function q(m, o) {
    return typeof m == "object" && m !== null && m.key != null ? h("" + m.key) : o.toString(36);
  }
  function L(m) {
    switch (m.status) {
      case "fulfilled":
        return m.value;
      case "rejected":
        throw m.reason;
      default:
        switch (typeof m.status == "string" ? m.then(le, le) : (m.status = "pending", m.then(
          function(o) {
            m.status === "pending" && (m.status = "fulfilled", m.value = o);
          },
          function(o) {
            m.status === "pending" && (m.status = "rejected", m.reason = o);
          }
        )), m.status) {
          case "fulfilled":
            return m.value;
          case "rejected":
            throw m.reason;
        }
    }
    throw m;
  }
  function S(m, o, D, G, ae) {
    var ce = typeof m;
    (ce === "undefined" || ce === "boolean") && (m = null);
    var de = !1;
    if (m === null) de = !0;
    else
      switch (ce) {
        case "bigint":
        case "string":
        case "number":
          de = !0;
          break;
        case "object":
          switch (m.$$typeof) {
            case s:
            case d:
              de = !0;
              break;
            case _:
              return de = m._init, S(
                de(m._payload),
                o,
                D,
                G,
                ae
              );
          }
      }
    if (de)
      return ae = ae(m), de = G === "" ? "." + q(m, 0) : G, te(ae) ? (D = "", de != null && (D = de.replace(x, "$&/") + "/"), S(ae, o, D, "", function(nt) {
        return nt;
      })) : ae != null && (H(ae) && (ae = fe(
        ae,
        D + (ae.key == null || m && m.key === ae.key ? "" : ("" + ae.key).replace(
          x,
          "$&/"
        ) + "/") + de
      )), o.push(ae)), 1;
    de = 0;
    var Oe = G === "" ? "." : G + ":";
    if (te(m))
      for (var _e = 0; _e < m.length; _e++)
        G = m[_e], ce = Oe + q(G, _e), de += S(
          G,
          o,
          D,
          ce,
          ae
        );
    else if (_e = Y(m), typeof _e == "function")
      for (m = _e.call(m), _e = 0; !(G = m.next()).done; )
        G = G.value, ce = Oe + q(G, _e++), de += S(
          G,
          o,
          D,
          ce,
          ae
        );
    else if (ce === "object") {
      if (typeof m.then == "function")
        return S(
          L(m),
          o,
          D,
          G,
          ae
        );
      throw o = String(m), Error(
        "Objects are not valid as a React child (found: " + (o === "[object Object]" ? "object with keys {" + Object.keys(m).join(", ") + "}" : o) + "). If you meant to render a collection of children, use an array instead."
      );
    }
    return de;
  }
  function Z(m, o, D) {
    if (m == null) return m;
    var G = [], ae = 0;
    return S(m, G, "", "", function(ce) {
      return o.call(D, ce, ae++);
    }), G;
  }
  function ne(m) {
    if (m._status === -1) {
      var o = m._result;
      o = o(), o.then(
        function(D) {
          (m._status === 0 || m._status === -1) && (m._status = 1, m._result = D);
        },
        function(D) {
          (m._status === 0 || m._status === -1) && (m._status = 2, m._result = D);
        }
      ), m._status === -1 && (m._status = 0, m._result = o);
    }
    if (m._status === 1) return m._result.default;
    throw m._result;
  }
  var me = typeof reportError == "function" ? reportError : function(m) {
    if (typeof window == "object" && typeof window.ErrorEvent == "function") {
      var o = new window.ErrorEvent("error", {
        bubbles: !0,
        cancelable: !0,
        message: typeof m == "object" && m !== null && typeof m.message == "string" ? String(m.message) : String(m),
        error: m
      });
      if (!window.dispatchEvent(o)) return;
    } else if (typeof process == "object" && typeof process.emit == "function") {
      process.emit("uncaughtException", m);
      return;
    }
    console.error(m);
  }, w = {
    map: Z,
    forEach: function(m, o, D) {
      Z(
        m,
        function() {
          o.apply(this, arguments);
        },
        D
      );
    },
    count: function(m) {
      var o = 0;
      return Z(m, function() {
        o++;
      }), o;
    },
    toArray: function(m) {
      return Z(m, function(o) {
        return o;
      }) || [];
    },
    only: function(m) {
      if (!H(m))
        throw Error(
          "React.Children.only expected to receive a single React element child."
        );
      return m;
    }
  };
  return be.Activity = y, be.Children = w, be.Component = J, be.Fragment = b, be.Profiler = E, be.PureComponent = V, be.StrictMode = f, be.Suspense = M, be.__CLIENT_INTERNALS_DO_NOT_USE_OR_WARN_USERS_THEY_CANNOT_UPGRADE = P, be.__COMPILER_RUNTIME = {
    __proto__: null,
    c: function(m) {
      return P.H.useMemoCache(m);
    }
  }, be.cache = function(m) {
    return function() {
      return m.apply(null, arguments);
    };
  }, be.cacheSignal = function() {
    return null;
  }, be.cloneElement = function(m, o, D) {
    if (m == null)
      throw Error(
        "The argument must be a React element, but you passed " + m + "."
      );
    var G = I({}, m.props), ae = m.key;
    if (o != null)
      for (ce in o.key !== void 0 && (ae = "" + o.key), o)
        !F.call(o, ce) || ce === "key" || ce === "__self" || ce === "__source" || ce === "ref" && o.ref === void 0 || (G[ce] = o[ce]);
    var ce = arguments.length - 2;
    if (ce === 1) G.children = D;
    else if (1 < ce) {
      for (var de = Array(ce), Oe = 0; Oe < ce; Oe++)
        de[Oe] = arguments[Oe + 2];
      G.children = de;
    }
    return ee(m.type, ae, G);
  }, be.createContext = function(m) {
    return m = {
      $$typeof: O,
      _currentValue: m,
      _currentValue2: m,
      _threadCount: 0,
      Provider: null,
      Consumer: null
    }, m.Provider = m, m.Consumer = {
      $$typeof: z,
      _context: m
    }, m;
  }, be.createElement = function(m, o, D) {
    var G, ae = {}, ce = null;
    if (o != null)
      for (G in o.key !== void 0 && (ce = "" + o.key), o)
        F.call(o, G) && G !== "key" && G !== "__self" && G !== "__source" && (ae[G] = o[G]);
    var de = arguments.length - 2;
    if (de === 1) ae.children = D;
    else if (1 < de) {
      for (var Oe = Array(de), _e = 0; _e < de; _e++)
        Oe[_e] = arguments[_e + 2];
      ae.children = Oe;
    }
    if (m && m.defaultProps)
      for (G in de = m.defaultProps, de)
        ae[G] === void 0 && (ae[G] = de[G]);
    return ee(m, ce, ae);
  }, be.createRef = function() {
    return { current: null };
  }, be.forwardRef = function(m) {
    return { $$typeof: U, render: m };
  }, be.isValidElement = H, be.lazy = function(m) {
    return {
      $$typeof: _,
      _payload: { _status: -1, _result: m },
      _init: ne
    };
  }, be.memo = function(m, o) {
    return {
      $$typeof: j,
      type: m,
      compare: o === void 0 ? null : o
    };
  }, be.startTransition = function(m) {
    var o = P.T, D = {};
    P.T = D;
    try {
      var G = m(), ae = P.S;
      ae !== null && ae(D, G), typeof G == "object" && G !== null && typeof G.then == "function" && G.then(le, me);
    } catch (ce) {
      me(ce);
    } finally {
      o !== null && D.types !== null && (o.types = D.types), P.T = o;
    }
  }, be.unstable_useCacheRefresh = function() {
    return P.H.useCacheRefresh();
  }, be.use = function(m) {
    return P.H.use(m);
  }, be.useActionState = function(m, o, D) {
    return P.H.useActionState(m, o, D);
  }, be.useCallback = function(m, o) {
    return P.H.useCallback(m, o);
  }, be.useContext = function(m) {
    return P.H.useContext(m);
  }, be.useDebugValue = function() {
  }, be.useDeferredValue = function(m, o) {
    return P.H.useDeferredValue(m, o);
  }, be.useEffect = function(m, o) {
    return P.H.useEffect(m, o);
  }, be.useEffectEvent = function(m) {
    return P.H.useEffectEvent(m);
  }, be.useId = function() {
    return P.H.useId();
  }, be.useImperativeHandle = function(m, o, D) {
    return P.H.useImperativeHandle(m, o, D);
  }, be.useInsertionEffect = function(m, o) {
    return P.H.useInsertionEffect(m, o);
  }, be.useLayoutEffect = function(m, o) {
    return P.H.useLayoutEffect(m, o);
  }, be.useMemo = function(m, o) {
    return P.H.useMemo(m, o);
  }, be.useOptimistic = function(m, o) {
    return P.H.useOptimistic(m, o);
  }, be.useReducer = function(m, o, D) {
    return P.H.useReducer(m, o, D);
  }, be.useRef = function(m) {
    return P.H.useRef(m);
  }, be.useState = function(m) {
    return P.H.useState(m);
  }, be.useSyncExternalStore = function(m, o, D) {
    return P.H.useSyncExternalStore(
      m,
      o,
      D
    );
  }, be.useTransition = function() {
    return P.H.useTransition();
  }, be.version = "19.2.0", be;
}
var kd;
function Kc() {
  return kd || (kd = 1, Bc.exports = R0()), Bc.exports;
}
var T = Kc();
const wc = /* @__PURE__ */ C0(T);
var Yc = { exports: {} }, Pn = {}, Gc = { exports: {} }, Xc = {};
/**
 * @license React
 * scheduler.production.js
 *
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
var $d;
function U0() {
  return $d || ($d = 1, (function(s) {
    function d(S, Z) {
      var ne = S.length;
      S.push(Z);
      e: for (; 0 < ne; ) {
        var me = ne - 1 >>> 1, w = S[me];
        if (0 < E(w, Z))
          S[me] = Z, S[ne] = w, ne = me;
        else break e;
      }
    }
    function b(S) {
      return S.length === 0 ? null : S[0];
    }
    function f(S) {
      if (S.length === 0) return null;
      var Z = S[0], ne = S.pop();
      if (ne !== Z) {
        S[0] = ne;
        e: for (var me = 0, w = S.length, m = w >>> 1; me < m; ) {
          var o = 2 * (me + 1) - 1, D = S[o], G = o + 1, ae = S[G];
          if (0 > E(D, ne))
            G < w && 0 > E(ae, D) ? (S[me] = ae, S[G] = ne, me = G) : (S[me] = D, S[o] = ne, me = o);
          else if (G < w && 0 > E(ae, ne))
            S[me] = ae, S[G] = ne, me = G;
          else break e;
        }
      }
      return Z;
    }
    function E(S, Z) {
      var ne = S.sortIndex - Z.sortIndex;
      return ne !== 0 ? ne : S.id - Z.id;
    }
    if (s.unstable_now = void 0, typeof performance == "object" && typeof performance.now == "function") {
      var z = performance;
      s.unstable_now = function() {
        return z.now();
      };
    } else {
      var O = Date, U = O.now();
      s.unstable_now = function() {
        return O.now() - U;
      };
    }
    var M = [], j = [], _ = 1, y = null, p = 3, Y = !1, X = !1, I = !1, ie = !1, J = typeof setTimeout == "function" ? setTimeout : null, ue = typeof clearTimeout == "function" ? clearTimeout : null, V = typeof setImmediate < "u" ? setImmediate : null;
    function W(S) {
      for (var Z = b(j); Z !== null; ) {
        if (Z.callback === null) f(j);
        else if (Z.startTime <= S)
          f(j), Z.sortIndex = Z.expirationTime, d(M, Z);
        else break;
        Z = b(j);
      }
    }
    function te(S) {
      if (I = !1, W(S), !X)
        if (b(M) !== null)
          X = !0, le || (le = !0, h());
        else {
          var Z = b(j);
          Z !== null && L(te, Z.startTime - S);
        }
    }
    var le = !1, P = -1, F = 5, ee = -1;
    function fe() {
      return ie ? !0 : !(s.unstable_now() - ee < F);
    }
    function H() {
      if (ie = !1, le) {
        var S = s.unstable_now();
        ee = S;
        var Z = !0;
        try {
          e: {
            X = !1, I && (I = !1, ue(P), P = -1), Y = !0;
            var ne = p;
            try {
              t: {
                for (W(S), y = b(M); y !== null && !(y.expirationTime > S && fe()); ) {
                  var me = y.callback;
                  if (typeof me == "function") {
                    y.callback = null, p = y.priorityLevel;
                    var w = me(
                      y.expirationTime <= S
                    );
                    if (S = s.unstable_now(), typeof w == "function") {
                      y.callback = w, W(S), Z = !0;
                      break t;
                    }
                    y === b(M) && f(M), W(S);
                  } else f(M);
                  y = b(M);
                }
                if (y !== null) Z = !0;
                else {
                  var m = b(j);
                  m !== null && L(
                    te,
                    m.startTime - S
                  ), Z = !1;
                }
              }
              break e;
            } finally {
              y = null, p = ne, Y = !1;
            }
            Z = void 0;
          }
        } finally {
          Z ? h() : le = !1;
        }
      }
    }
    var h;
    if (typeof V == "function")
      h = function() {
        V(H);
      };
    else if (typeof MessageChannel < "u") {
      var x = new MessageChannel(), q = x.port2;
      x.port1.onmessage = H, h = function() {
        q.postMessage(null);
      };
    } else
      h = function() {
        J(H, 0);
      };
    function L(S, Z) {
      P = J(function() {
        S(s.unstable_now());
      }, Z);
    }
    s.unstable_IdlePriority = 5, s.unstable_ImmediatePriority = 1, s.unstable_LowPriority = 4, s.unstable_NormalPriority = 3, s.unstable_Profiling = null, s.unstable_UserBlockingPriority = 2, s.unstable_cancelCallback = function(S) {
      S.callback = null;
    }, s.unstable_forceFrameRate = function(S) {
      0 > S || 125 < S ? console.error(
        "forceFrameRate takes a positive int between 0 and 125, forcing frame rates higher than 125 fps is not supported"
      ) : F = 0 < S ? Math.floor(1e3 / S) : 5;
    }, s.unstable_getCurrentPriorityLevel = function() {
      return p;
    }, s.unstable_next = function(S) {
      switch (p) {
        case 1:
        case 2:
        case 3:
          var Z = 3;
          break;
        default:
          Z = p;
      }
      var ne = p;
      p = Z;
      try {
        return S();
      } finally {
        p = ne;
      }
    }, s.unstable_requestPaint = function() {
      ie = !0;
    }, s.unstable_runWithPriority = function(S, Z) {
      switch (S) {
        case 1:
        case 2:
        case 3:
        case 4:
        case 5:
          break;
        default:
          S = 3;
      }
      var ne = p;
      p = S;
      try {
        return Z();
      } finally {
        p = ne;
      }
    }, s.unstable_scheduleCallback = function(S, Z, ne) {
      var me = s.unstable_now();
      switch (typeof ne == "object" && ne !== null ? (ne = ne.delay, ne = typeof ne == "number" && 0 < ne ? me + ne : me) : ne = me, S) {
        case 1:
          var w = -1;
          break;
        case 2:
          w = 250;
          break;
        case 5:
          w = 1073741823;
          break;
        case 4:
          w = 1e4;
          break;
        default:
          w = 5e3;
      }
      return w = ne + w, S = {
        id: _++,
        callback: Z,
        priorityLevel: S,
        startTime: ne,
        expirationTime: w,
        sortIndex: -1
      }, ne > me ? (S.sortIndex = ne, d(j, S), b(M) === null && S === b(j) && (I ? (ue(P), P = -1) : I = !0, L(te, ne - me))) : (S.sortIndex = w, d(M, S), X || Y || (X = !0, le || (le = !0, h()))), S;
    }, s.unstable_shouldYield = fe, s.unstable_wrapCallback = function(S) {
      var Z = p;
      return function() {
        var ne = p;
        p = Z;
        try {
          return S.apply(this, arguments);
        } finally {
          p = ne;
        }
      };
    };
  })(Xc)), Xc;
}
var Fd;
function H0() {
  return Fd || (Fd = 1, Gc.exports = U0()), Gc.exports;
}
var Lc = { exports: {} }, ft = {};
/**
 * @license React
 * react-dom.production.js
 *
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
var Wd;
function q0() {
  if (Wd) return ft;
  Wd = 1;
  var s = Kc();
  function d(M) {
    var j = "https://react.dev/errors/" + M;
    if (1 < arguments.length) {
      j += "?args[]=" + encodeURIComponent(arguments[1]);
      for (var _ = 2; _ < arguments.length; _++)
        j += "&args[]=" + encodeURIComponent(arguments[_]);
    }
    return "Minified React error #" + M + "; visit " + j + " for the full message or use the non-minified dev environment for full errors and additional helpful warnings.";
  }
  function b() {
  }
  var f = {
    d: {
      f: b,
      r: function() {
        throw Error(d(522));
      },
      D: b,
      C: b,
      L: b,
      m: b,
      X: b,
      S: b,
      M: b
    },
    p: 0,
    findDOMNode: null
  }, E = Symbol.for("react.portal");
  function z(M, j, _) {
    var y = 3 < arguments.length && arguments[3] !== void 0 ? arguments[3] : null;
    return {
      $$typeof: E,
      key: y == null ? null : "" + y,
      children: M,
      containerInfo: j,
      implementation: _
    };
  }
  var O = s.__CLIENT_INTERNALS_DO_NOT_USE_OR_WARN_USERS_THEY_CANNOT_UPGRADE;
  function U(M, j) {
    if (M === "font") return "";
    if (typeof j == "string")
      return j === "use-credentials" ? j : "";
  }
  return ft.__DOM_INTERNALS_DO_NOT_USE_OR_WARN_USERS_THEY_CANNOT_UPGRADE = f, ft.createPortal = function(M, j) {
    var _ = 2 < arguments.length && arguments[2] !== void 0 ? arguments[2] : null;
    if (!j || j.nodeType !== 1 && j.nodeType !== 9 && j.nodeType !== 11)
      throw Error(d(299));
    return z(M, j, null, _);
  }, ft.flushSync = function(M) {
    var j = O.T, _ = f.p;
    try {
      if (O.T = null, f.p = 2, M) return M();
    } finally {
      O.T = j, f.p = _, f.d.f();
    }
  }, ft.preconnect = function(M, j) {
    typeof M == "string" && (j ? (j = j.crossOrigin, j = typeof j == "string" ? j === "use-credentials" ? j : "" : void 0) : j = null, f.d.C(M, j));
  }, ft.prefetchDNS = function(M) {
    typeof M == "string" && f.d.D(M);
  }, ft.preinit = function(M, j) {
    if (typeof M == "string" && j && typeof j.as == "string") {
      var _ = j.as, y = U(_, j.crossOrigin), p = typeof j.integrity == "string" ? j.integrity : void 0, Y = typeof j.fetchPriority == "string" ? j.fetchPriority : void 0;
      _ === "style" ? f.d.S(
        M,
        typeof j.precedence == "string" ? j.precedence : void 0,
        {
          crossOrigin: y,
          integrity: p,
          fetchPriority: Y
        }
      ) : _ === "script" && f.d.X(M, {
        crossOrigin: y,
        integrity: p,
        fetchPriority: Y,
        nonce: typeof j.nonce == "string" ? j.nonce : void 0
      });
    }
  }, ft.preinitModule = function(M, j) {
    if (typeof M == "string")
      if (typeof j == "object" && j !== null) {
        if (j.as == null || j.as === "script") {
          var _ = U(
            j.as,
            j.crossOrigin
          );
          f.d.M(M, {
            crossOrigin: _,
            integrity: typeof j.integrity == "string" ? j.integrity : void 0,
            nonce: typeof j.nonce == "string" ? j.nonce : void 0
          });
        }
      } else j == null && f.d.M(M);
  }, ft.preload = function(M, j) {
    if (typeof M == "string" && typeof j == "object" && j !== null && typeof j.as == "string") {
      var _ = j.as, y = U(_, j.crossOrigin);
      f.d.L(M, _, {
        crossOrigin: y,
        integrity: typeof j.integrity == "string" ? j.integrity : void 0,
        nonce: typeof j.nonce == "string" ? j.nonce : void 0,
        type: typeof j.type == "string" ? j.type : void 0,
        fetchPriority: typeof j.fetchPriority == "string" ? j.fetchPriority : void 0,
        referrerPolicy: typeof j.referrerPolicy == "string" ? j.referrerPolicy : void 0,
        imageSrcSet: typeof j.imageSrcSet == "string" ? j.imageSrcSet : void 0,
        imageSizes: typeof j.imageSizes == "string" ? j.imageSizes : void 0,
        media: typeof j.media == "string" ? j.media : void 0
      });
    }
  }, ft.preloadModule = function(M, j) {
    if (typeof M == "string")
      if (j) {
        var _ = U(j.as, j.crossOrigin);
        f.d.m(M, {
          as: typeof j.as == "string" && j.as !== "script" ? j.as : void 0,
          crossOrigin: _,
          integrity: typeof j.integrity == "string" ? j.integrity : void 0
        });
      } else f.d.m(M);
  }, ft.requestFormReset = function(M) {
    f.d.r(M);
  }, ft.unstable_batchedUpdates = function(M, j) {
    return M(j);
  }, ft.useFormState = function(M, j, _) {
    return O.H.useFormState(M, j, _);
  }, ft.useFormStatus = function() {
    return O.H.useHostTransitionStatus();
  }, ft.version = "19.2.0", ft;
}
var Id;
function sm() {
  if (Id) return Lc.exports;
  Id = 1;
  function s() {
    if (!(typeof __REACT_DEVTOOLS_GLOBAL_HOOK__ > "u" || typeof __REACT_DEVTOOLS_GLOBAL_HOOK__.checkDCE != "function"))
      try {
        __REACT_DEVTOOLS_GLOBAL_HOOK__.checkDCE(s);
      } catch (d) {
        console.error(d);
      }
  }
  return s(), Lc.exports = q0(), Lc.exports;
}
/**
 * @license React
 * react-dom-client.production.js
 *
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
var Pd;
function B0() {
  if (Pd) return Pn;
  Pd = 1;
  var s = H0(), d = Kc(), b = sm();
  function f(e) {
    var t = "https://react.dev/errors/" + e;
    if (1 < arguments.length) {
      t += "?args[]=" + encodeURIComponent(arguments[1]);
      for (var l = 2; l < arguments.length; l++)
        t += "&args[]=" + encodeURIComponent(arguments[l]);
    }
    return "Minified React error #" + e + "; visit " + t + " for the full message or use the non-minified dev environment for full errors and additional helpful warnings.";
  }
  function E(e) {
    return !(!e || e.nodeType !== 1 && e.nodeType !== 9 && e.nodeType !== 11);
  }
  function z(e) {
    var t = e, l = e;
    if (e.alternate) for (; t.return; ) t = t.return;
    else {
      e = t;
      do
        t = e, (t.flags & 4098) !== 0 && (l = t.return), e = t.return;
      while (e);
    }
    return t.tag === 3 ? l : null;
  }
  function O(e) {
    if (e.tag === 13) {
      var t = e.memoizedState;
      if (t === null && (e = e.alternate, e !== null && (t = e.memoizedState)), t !== null) return t.dehydrated;
    }
    return null;
  }
  function U(e) {
    if (e.tag === 31) {
      var t = e.memoizedState;
      if (t === null && (e = e.alternate, e !== null && (t = e.memoizedState)), t !== null) return t.dehydrated;
    }
    return null;
  }
  function M(e) {
    if (z(e) !== e)
      throw Error(f(188));
  }
  function j(e) {
    var t = e.alternate;
    if (!t) {
      if (t = z(e), t === null) throw Error(f(188));
      return t !== e ? null : e;
    }
    for (var l = e, a = t; ; ) {
      var u = l.return;
      if (u === null) break;
      var i = u.alternate;
      if (i === null) {
        if (a = u.return, a !== null) {
          l = a;
          continue;
        }
        break;
      }
      if (u.child === i.child) {
        for (i = u.child; i; ) {
          if (i === l) return M(u), e;
          if (i === a) return M(u), t;
          i = i.sibling;
        }
        throw Error(f(188));
      }
      if (l.return !== a.return) l = u, a = i;
      else {
        for (var c = !1, r = u.child; r; ) {
          if (r === l) {
            c = !0, l = u, a = i;
            break;
          }
          if (r === a) {
            c = !0, a = u, l = i;
            break;
          }
          r = r.sibling;
        }
        if (!c) {
          for (r = i.child; r; ) {
            if (r === l) {
              c = !0, l = i, a = u;
              break;
            }
            if (r === a) {
              c = !0, a = i, l = u;
              break;
            }
            r = r.sibling;
          }
          if (!c) throw Error(f(189));
        }
      }
      if (l.alternate !== a) throw Error(f(190));
    }
    if (l.tag !== 3) throw Error(f(188));
    return l.stateNode.current === l ? e : t;
  }
  function _(e) {
    var t = e.tag;
    if (t === 5 || t === 26 || t === 27 || t === 6) return e;
    for (e = e.child; e !== null; ) {
      if (t = _(e), t !== null) return t;
      e = e.sibling;
    }
    return null;
  }
  var y = Object.assign, p = Symbol.for("react.element"), Y = Symbol.for("react.transitional.element"), X = Symbol.for("react.portal"), I = Symbol.for("react.fragment"), ie = Symbol.for("react.strict_mode"), J = Symbol.for("react.profiler"), ue = Symbol.for("react.consumer"), V = Symbol.for("react.context"), W = Symbol.for("react.forward_ref"), te = Symbol.for("react.suspense"), le = Symbol.for("react.suspense_list"), P = Symbol.for("react.memo"), F = Symbol.for("react.lazy"), ee = Symbol.for("react.activity"), fe = Symbol.for("react.memo_cache_sentinel"), H = Symbol.iterator;
  function h(e) {
    return e === null || typeof e != "object" ? null : (e = H && e[H] || e["@@iterator"], typeof e == "function" ? e : null);
  }
  var x = Symbol.for("react.client.reference");
  function q(e) {
    if (e == null) return null;
    if (typeof e == "function")
      return e.$$typeof === x ? null : e.displayName || e.name || null;
    if (typeof e == "string") return e;
    switch (e) {
      case I:
        return "Fragment";
      case J:
        return "Profiler";
      case ie:
        return "StrictMode";
      case te:
        return "Suspense";
      case le:
        return "SuspenseList";
      case ee:
        return "Activity";
    }
    if (typeof e == "object")
      switch (e.$$typeof) {
        case X:
          return "Portal";
        case V:
          return e.displayName || "Context";
        case ue:
          return (e._context.displayName || "Context") + ".Consumer";
        case W:
          var t = e.render;
          return e = e.displayName, e || (e = t.displayName || t.name || "", e = e !== "" ? "ForwardRef(" + e + ")" : "ForwardRef"), e;
        case P:
          return t = e.displayName || null, t !== null ? t : q(e.type) || "Memo";
        case F:
          t = e._payload, e = e._init;
          try {
            return q(e(t));
          } catch {
          }
      }
    return null;
  }
  var L = Array.isArray, S = d.__CLIENT_INTERNALS_DO_NOT_USE_OR_WARN_USERS_THEY_CANNOT_UPGRADE, Z = b.__DOM_INTERNALS_DO_NOT_USE_OR_WARN_USERS_THEY_CANNOT_UPGRADE, ne = {
    pending: !1,
    data: null,
    method: null,
    action: null
  }, me = [], w = -1;
  function m(e) {
    return { current: e };
  }
  function o(e) {
    0 > w || (e.current = me[w], me[w] = null, w--);
  }
  function D(e, t) {
    w++, me[w] = e.current, e.current = t;
  }
  var G = m(null), ae = m(null), ce = m(null), de = m(null);
  function Oe(e, t) {
    switch (D(ce, t), D(ae, e), D(G, null), t.nodeType) {
      case 9:
      case 11:
        e = (e = t.documentElement) && (e = e.namespaceURI) ? hd(e) : 0;
        break;
      default:
        if (e = t.tagName, t = t.namespaceURI)
          t = hd(t), e = vd(t, e);
        else
          switch (e) {
            case "svg":
              e = 1;
              break;
            case "math":
              e = 2;
              break;
            default:
              e = 0;
          }
    }
    o(G), D(G, e);
  }
  function _e() {
    o(G), o(ae), o(ce);
  }
  function nt(e) {
    e.memoizedState !== null && D(de, e);
    var t = G.current, l = vd(t, e.type);
    t !== l && (D(ae, e), D(G, l));
  }
  function ve(e) {
    ae.current === e && (o(G), o(ae)), de.current === e && (o(de), kn._currentValue = ne);
  }
  var ge, qe;
  function ye(e) {
    if (ge === void 0)
      try {
        throw Error();
      } catch (l) {
        var t = l.stack.trim().match(/\n( *(at )?)/);
        ge = t && t[1] || "", qe = -1 < l.stack.indexOf(`
    at`) ? " (<anonymous>)" : -1 < l.stack.indexOf("@") ? "@unknown:0:0" : "";
      }
    return `
` + ge + e + qe;
  }
  var pe = !1;
  function vt(e, t) {
    if (!e || pe) return "";
    pe = !0;
    var l = Error.prepareStackTrace;
    Error.prepareStackTrace = void 0;
    try {
      var a = {
        DetermineComponentFrameRoot: function() {
          try {
            if (t) {
              var $ = function() {
                throw Error();
              };
              if (Object.defineProperty($.prototype, "props", {
                set: function() {
                  throw Error();
                }
              }), typeof Reflect == "object" && Reflect.construct) {
                try {
                  Reflect.construct($, []);
                } catch (B) {
                  var R = B;
                }
                Reflect.construct(e, [], $);
              } else {
                try {
                  $.call();
                } catch (B) {
                  R = B;
                }
                e.call($.prototype);
              }
            } else {
              try {
                throw Error();
              } catch (B) {
                R = B;
              }
              ($ = e()) && typeof $.catch == "function" && $.catch(function() {
              });
            }
          } catch (B) {
            if (B && R && typeof B.stack == "string")
              return [B.stack, R.stack];
          }
          return [null, null];
        }
      };
      a.DetermineComponentFrameRoot.displayName = "DetermineComponentFrameRoot";
      var u = Object.getOwnPropertyDescriptor(
        a.DetermineComponentFrameRoot,
        "name"
      );
      u && u.configurable && Object.defineProperty(
        a.DetermineComponentFrameRoot,
        "name",
        { value: "DetermineComponentFrameRoot" }
      );
      var i = a.DetermineComponentFrameRoot(), c = i[0], r = i[1];
      if (c && r) {
        var v = c.split(`
`), C = r.split(`
`);
        for (u = a = 0; a < v.length && !v[a].includes("DetermineComponentFrameRoot"); )
          a++;
        for (; u < C.length && !C[u].includes(
          "DetermineComponentFrameRoot"
        ); )
          u++;
        if (a === v.length || u === C.length)
          for (a = v.length - 1, u = C.length - 1; 1 <= a && 0 <= u && v[a] !== C[u]; )
            u--;
        for (; 1 <= a && 0 <= u; a--, u--)
          if (v[a] !== C[u]) {
            if (a !== 1 || u !== 1)
              do
                if (a--, u--, 0 > u || v[a] !== C[u]) {
                  var Q = `
` + v[a].replace(" at new ", " at ");
                  return e.displayName && Q.includes("<anonymous>") && (Q = Q.replace("<anonymous>", e.displayName)), Q;
                }
              while (1 <= a && 0 <= u);
            break;
          }
      }
    } finally {
      pe = !1, Error.prepareStackTrace = l;
    }
    return (l = e ? e.displayName || e.name : "") ? ye(l) : "";
  }
  function Ve(e, t) {
    switch (e.tag) {
      case 26:
      case 27:
      case 5:
        return ye(e.type);
      case 16:
        return ye("Lazy");
      case 13:
        return e.child !== t && t !== null ? ye("Suspense Fallback") : ye("Suspense");
      case 19:
        return ye("SuspenseList");
      case 0:
      case 15:
        return vt(e.type, !1);
      case 11:
        return vt(e.type.render, !1);
      case 1:
        return vt(e.type, !0);
      case 31:
        return ye("Activity");
      default:
        return "";
    }
  }
  function Re(e) {
    try {
      var t = "", l = null;
      do
        t += Ve(e, l), l = e, e = e.return;
      while (e);
      return t;
    } catch (a) {
      return `
Error generating stack: ` + a.message + `
` + a.stack;
    }
  }
  var Dt = Object.prototype.hasOwnProperty, Jl = s.unstable_scheduleCallback, kl = s.unstable_cancelCallback, yl = s.unstable_shouldYield, Si = s.unstable_requestPaint, mt = s.unstable_now, k = s.unstable_getCurrentPriorityLevel, Se = s.unstable_ImmediatePriority, tt = s.unstable_UserBlockingPriority, Pt = s.unstable_NormalPriority, $l = s.unstable_LowPriority, va = s.unstable_IdlePriority, dm = s.log, mm = s.unstable_setDisableYieldValue, un = null, St = null;
  function xl(e) {
    if (typeof dm == "function" && mm(e), St && typeof St.setStrictMode == "function")
      try {
        St.setStrictMode(un, e);
      } catch {
      }
  }
  var Nt = Math.clz32 ? Math.clz32 : pm, hm = Math.log, vm = Math.LN2;
  function pm(e) {
    return e >>>= 0, e === 0 ? 32 : 31 - (hm(e) / vm | 0) | 0;
  }
  var lu = 256, au = 262144, nu = 4194304;
  function Fl(e) {
    var t = e & 42;
    if (t !== 0) return t;
    switch (e & -e) {
      case 1:
        return 1;
      case 2:
        return 2;
      case 4:
        return 4;
      case 8:
        return 8;
      case 16:
        return 16;
      case 32:
        return 32;
      case 64:
        return 64;
      case 128:
        return 128;
      case 256:
      case 512:
      case 1024:
      case 2048:
      case 4096:
      case 8192:
      case 16384:
      case 32768:
      case 65536:
      case 131072:
        return e & 261888;
      case 262144:
      case 524288:
      case 1048576:
      case 2097152:
        return e & 3932160;
      case 4194304:
      case 8388608:
      case 16777216:
      case 33554432:
        return e & 62914560;
      case 67108864:
        return 67108864;
      case 134217728:
        return 134217728;
      case 268435456:
        return 268435456;
      case 536870912:
        return 536870912;
      case 1073741824:
        return 0;
      default:
        return e;
    }
  }
  function uu(e, t, l) {
    var a = e.pendingLanes;
    if (a === 0) return 0;
    var u = 0, i = e.suspendedLanes, c = e.pingedLanes;
    e = e.warmLanes;
    var r = a & 134217727;
    return r !== 0 ? (a = r & ~i, a !== 0 ? u = Fl(a) : (c &= r, c !== 0 ? u = Fl(c) : l || (l = r & ~e, l !== 0 && (u = Fl(l))))) : (r = a & ~i, r !== 0 ? u = Fl(r) : c !== 0 ? u = Fl(c) : l || (l = a & ~e, l !== 0 && (u = Fl(l)))), u === 0 ? 0 : t !== 0 && t !== u && (t & i) === 0 && (i = u & -u, l = t & -t, i >= l || i === 32 && (l & 4194048) !== 0) ? t : u;
  }
  function sn(e, t) {
    return (e.pendingLanes & ~(e.suspendedLanes & ~e.pingedLanes) & t) === 0;
  }
  function bm(e, t) {
    switch (e) {
      case 1:
      case 2:
      case 4:
      case 8:
      case 64:
        return t + 250;
      case 16:
      case 32:
      case 128:
      case 256:
      case 512:
      case 1024:
      case 2048:
      case 4096:
      case 8192:
      case 16384:
      case 32768:
      case 65536:
      case 131072:
      case 262144:
      case 524288:
      case 1048576:
      case 2097152:
        return t + 5e3;
      case 4194304:
      case 8388608:
      case 16777216:
      case 33554432:
        return -1;
      case 67108864:
      case 134217728:
      case 268435456:
      case 536870912:
      case 1073741824:
        return -1;
      default:
        return -1;
    }
  }
  function Jc() {
    var e = nu;
    return nu <<= 1, (nu & 62914560) === 0 && (nu = 4194304), e;
  }
  function Ni(e) {
    for (var t = [], l = 0; 31 > l; l++) t.push(e);
    return t;
  }
  function cn(e, t) {
    e.pendingLanes |= t, t !== 268435456 && (e.suspendedLanes = 0, e.pingedLanes = 0, e.warmLanes = 0);
  }
  function ym(e, t, l, a, u, i) {
    var c = e.pendingLanes;
    e.pendingLanes = l, e.suspendedLanes = 0, e.pingedLanes = 0, e.warmLanes = 0, e.expiredLanes &= l, e.entangledLanes &= l, e.errorRecoveryDisabledLanes &= l, e.shellSuspendCounter = 0;
    var r = e.entanglements, v = e.expirationTimes, C = e.hiddenUpdates;
    for (l = c & ~l; 0 < l; ) {
      var Q = 31 - Nt(l), $ = 1 << Q;
      r[Q] = 0, v[Q] = -1;
      var R = C[Q];
      if (R !== null)
        for (C[Q] = null, Q = 0; Q < R.length; Q++) {
          var B = R[Q];
          B !== null && (B.lane &= -536870913);
        }
      l &= ~$;
    }
    a !== 0 && kc(e, a, 0), i !== 0 && u === 0 && e.tag !== 0 && (e.suspendedLanes |= i & ~(c & ~t));
  }
  function kc(e, t, l) {
    e.pendingLanes |= t, e.suspendedLanes &= ~t;
    var a = 31 - Nt(t);
    e.entangledLanes |= t, e.entanglements[a] = e.entanglements[a] | 1073741824 | l & 261930;
  }
  function $c(e, t) {
    var l = e.entangledLanes |= t;
    for (e = e.entanglements; l; ) {
      var a = 31 - Nt(l), u = 1 << a;
      u & t | e[a] & t && (e[a] |= t), l &= ~u;
    }
  }
  function Fc(e, t) {
    var l = t & -t;
    return l = (l & 42) !== 0 ? 1 : Ei(l), (l & (e.suspendedLanes | t)) !== 0 ? 0 : l;
  }
  function Ei(e) {
    switch (e) {
      case 2:
        e = 1;
        break;
      case 8:
        e = 4;
        break;
      case 32:
        e = 16;
        break;
      case 256:
      case 512:
      case 1024:
      case 2048:
      case 4096:
      case 8192:
      case 16384:
      case 32768:
      case 65536:
      case 131072:
      case 262144:
      case 524288:
      case 1048576:
      case 2097152:
      case 4194304:
      case 8388608:
      case 16777216:
      case 33554432:
        e = 128;
        break;
      case 268435456:
        e = 134217728;
        break;
      default:
        e = 0;
    }
    return e;
  }
  function Mi(e) {
    return e &= -e, 2 < e ? 8 < e ? (e & 134217727) !== 0 ? 32 : 268435456 : 8 : 2;
  }
  function Wc() {
    var e = Z.p;
    return e !== 0 ? e : (e = window.event, e === void 0 ? 32 : Yd(e.type));
  }
  function Ic(e, t) {
    var l = Z.p;
    try {
      return Z.p = e, t();
    } finally {
      Z.p = l;
    }
  }
  var gl = Math.random().toString(36).slice(2), ut = "__reactFiber$" + gl, pt = "__reactProps$" + gl, pa = "__reactContainer$" + gl, zi = "__reactEvents$" + gl, xm = "__reactListeners$" + gl, gm = "__reactHandles$" + gl, Pc = "__reactResources$" + gl, rn = "__reactMarker$" + gl;
  function Ti(e) {
    delete e[ut], delete e[pt], delete e[zi], delete e[xm], delete e[gm];
  }
  function ba(e) {
    var t = e[ut];
    if (t) return t;
    for (var l = e.parentNode; l; ) {
      if (t = l[pa] || l[ut]) {
        if (l = t.alternate, t.child !== null || l !== null && l.child !== null)
          for (e = _d(e); e !== null; ) {
            if (l = e[ut]) return l;
            e = _d(e);
          }
        return t;
      }
      e = l, l = e.parentNode;
    }
    return null;
  }
  function ya(e) {
    if (e = e[ut] || e[pa]) {
      var t = e.tag;
      if (t === 5 || t === 6 || t === 13 || t === 31 || t === 26 || t === 27 || t === 3)
        return e;
    }
    return null;
  }
  function fn(e) {
    var t = e.tag;
    if (t === 5 || t === 26 || t === 27 || t === 6) return e.stateNode;
    throw Error(f(33));
  }
  function xa(e) {
    var t = e[Pc];
    return t || (t = e[Pc] = { hoistableStyles: /* @__PURE__ */ new Map(), hoistableScripts: /* @__PURE__ */ new Map() }), t;
  }
  function lt(e) {
    e[rn] = !0;
  }
  var er = /* @__PURE__ */ new Set(), tr = {};
  function Wl(e, t) {
    ga(e, t), ga(e + "Capture", t);
  }
  function ga(e, t) {
    for (tr[e] = t, e = 0; e < t.length; e++)
      er.add(t[e]);
  }
  var jm = RegExp(
    "^[:A-Z_a-z\\u00C0-\\u00D6\\u00D8-\\u00F6\\u00F8-\\u02FF\\u0370-\\u037D\\u037F-\\u1FFF\\u200C-\\u200D\\u2070-\\u218F\\u2C00-\\u2FEF\\u3001-\\uD7FF\\uF900-\\uFDCF\\uFDF0-\\uFFFD][:A-Z_a-z\\u00C0-\\u00D6\\u00D8-\\u00F6\\u00F8-\\u02FF\\u0370-\\u037D\\u037F-\\u1FFF\\u200C-\\u200D\\u2070-\\u218F\\u2C00-\\u2FEF\\u3001-\\uD7FF\\uF900-\\uFDCF\\uFDF0-\\uFFFD\\-.0-9\\u00B7\\u0300-\\u036F\\u203F-\\u2040]*$"
  ), lr = {}, ar = {};
  function _m(e) {
    return Dt.call(ar, e) ? !0 : Dt.call(lr, e) ? !1 : jm.test(e) ? ar[e] = !0 : (lr[e] = !0, !1);
  }
  function iu(e, t, l) {
    if (_m(t))
      if (l === null) e.removeAttribute(t);
      else {
        switch (typeof l) {
          case "undefined":
          case "function":
          case "symbol":
            e.removeAttribute(t);
            return;
          case "boolean":
            var a = t.toLowerCase().slice(0, 5);
            if (a !== "data-" && a !== "aria-") {
              e.removeAttribute(t);
              return;
            }
        }
        e.setAttribute(t, "" + l);
      }
  }
  function su(e, t, l) {
    if (l === null) e.removeAttribute(t);
    else {
      switch (typeof l) {
        case "undefined":
        case "function":
        case "symbol":
        case "boolean":
          e.removeAttribute(t);
          return;
      }
      e.setAttribute(t, "" + l);
    }
  }
  function el(e, t, l, a) {
    if (a === null) e.removeAttribute(l);
    else {
      switch (typeof a) {
        case "undefined":
        case "function":
        case "symbol":
        case "boolean":
          e.removeAttribute(l);
          return;
      }
      e.setAttributeNS(t, l, "" + a);
    }
  }
  function Rt(e) {
    switch (typeof e) {
      case "bigint":
      case "boolean":
      case "number":
      case "string":
      case "undefined":
        return e;
      case "object":
        return e;
      default:
        return "";
    }
  }
  function nr(e) {
    var t = e.type;
    return (e = e.nodeName) && e.toLowerCase() === "input" && (t === "checkbox" || t === "radio");
  }
  function Sm(e, t, l) {
    var a = Object.getOwnPropertyDescriptor(
      e.constructor.prototype,
      t
    );
    if (!e.hasOwnProperty(t) && typeof a < "u" && typeof a.get == "function" && typeof a.set == "function") {
      var u = a.get, i = a.set;
      return Object.defineProperty(e, t, {
        configurable: !0,
        get: function() {
          return u.call(this);
        },
        set: function(c) {
          l = "" + c, i.call(this, c);
        }
      }), Object.defineProperty(e, t, {
        enumerable: a.enumerable
      }), {
        getValue: function() {
          return l;
        },
        setValue: function(c) {
          l = "" + c;
        },
        stopTracking: function() {
          e._valueTracker = null, delete e[t];
        }
      };
    }
  }
  function Ai(e) {
    if (!e._valueTracker) {
      var t = nr(e) ? "checked" : "value";
      e._valueTracker = Sm(
        e,
        t,
        "" + e[t]
      );
    }
  }
  function ur(e) {
    if (!e) return !1;
    var t = e._valueTracker;
    if (!t) return !0;
    var l = t.getValue(), a = "";
    return e && (a = nr(e) ? e.checked ? "true" : "false" : e.value), e = a, e !== l ? (t.setValue(e), !0) : !1;
  }
  function cu(e) {
    if (e = e || (typeof document < "u" ? document : void 0), typeof e > "u") return null;
    try {
      return e.activeElement || e.body;
    } catch {
      return e.body;
    }
  }
  var Nm = /[\n"\\]/g;
  function Ut(e) {
    return e.replace(
      Nm,
      function(t) {
        return "\\" + t.charCodeAt(0).toString(16) + " ";
      }
    );
  }
  function Ci(e, t, l, a, u, i, c, r) {
    e.name = "", c != null && typeof c != "function" && typeof c != "symbol" && typeof c != "boolean" ? e.type = c : e.removeAttribute("type"), t != null ? c === "number" ? (t === 0 && e.value === "" || e.value != t) && (e.value = "" + Rt(t)) : e.value !== "" + Rt(t) && (e.value = "" + Rt(t)) : c !== "submit" && c !== "reset" || e.removeAttribute("value"), t != null ? Oi(e, c, Rt(t)) : l != null ? Oi(e, c, Rt(l)) : a != null && e.removeAttribute("value"), u == null && i != null && (e.defaultChecked = !!i), u != null && (e.checked = u && typeof u != "function" && typeof u != "symbol"), r != null && typeof r != "function" && typeof r != "symbol" && typeof r != "boolean" ? e.name = "" + Rt(r) : e.removeAttribute("name");
  }
  function ir(e, t, l, a, u, i, c, r) {
    if (i != null && typeof i != "function" && typeof i != "symbol" && typeof i != "boolean" && (e.type = i), t != null || l != null) {
      if (!(i !== "submit" && i !== "reset" || t != null)) {
        Ai(e);
        return;
      }
      l = l != null ? "" + Rt(l) : "", t = t != null ? "" + Rt(t) : l, r || t === e.value || (e.value = t), e.defaultValue = t;
    }
    a = a ?? u, a = typeof a != "function" && typeof a != "symbol" && !!a, e.checked = r ? e.checked : !!a, e.defaultChecked = !!a, c != null && typeof c != "function" && typeof c != "symbol" && typeof c != "boolean" && (e.name = c), Ai(e);
  }
  function Oi(e, t, l) {
    t === "number" && cu(e.ownerDocument) === e || e.defaultValue === "" + l || (e.defaultValue = "" + l);
  }
  function ja(e, t, l, a) {
    if (e = e.options, t) {
      t = {};
      for (var u = 0; u < l.length; u++)
        t["$" + l[u]] = !0;
      for (l = 0; l < e.length; l++)
        u = t.hasOwnProperty("$" + e[l].value), e[l].selected !== u && (e[l].selected = u), u && a && (e[l].defaultSelected = !0);
    } else {
      for (l = "" + Rt(l), t = null, u = 0; u < e.length; u++) {
        if (e[u].value === l) {
          e[u].selected = !0, a && (e[u].defaultSelected = !0);
          return;
        }
        t !== null || e[u].disabled || (t = e[u]);
      }
      t !== null && (t.selected = !0);
    }
  }
  function sr(e, t, l) {
    if (t != null && (t = "" + Rt(t), t !== e.value && (e.value = t), l == null)) {
      e.defaultValue !== t && (e.defaultValue = t);
      return;
    }
    e.defaultValue = l != null ? "" + Rt(l) : "";
  }
  function cr(e, t, l, a) {
    if (t == null) {
      if (a != null) {
        if (l != null) throw Error(f(92));
        if (L(a)) {
          if (1 < a.length) throw Error(f(93));
          a = a[0];
        }
        l = a;
      }
      l == null && (l = ""), t = l;
    }
    l = Rt(t), e.defaultValue = l, a = e.textContent, a === l && a !== "" && a !== null && (e.value = a), Ai(e);
  }
  function _a(e, t) {
    if (t) {
      var l = e.firstChild;
      if (l && l === e.lastChild && l.nodeType === 3) {
        l.nodeValue = t;
        return;
      }
    }
    e.textContent = t;
  }
  var Em = new Set(
    "animationIterationCount aspectRatio borderImageOutset borderImageSlice borderImageWidth boxFlex boxFlexGroup boxOrdinalGroup columnCount columns flex flexGrow flexPositive flexShrink flexNegative flexOrder gridArea gridRow gridRowEnd gridRowSpan gridRowStart gridColumn gridColumnEnd gridColumnSpan gridColumnStart fontWeight lineClamp lineHeight opacity order orphans scale tabSize widows zIndex zoom fillOpacity floodOpacity stopOpacity strokeDasharray strokeDashoffset strokeMiterlimit strokeOpacity strokeWidth MozAnimationIterationCount MozBoxFlex MozBoxFlexGroup MozLineClamp msAnimationIterationCount msFlex msZoom msFlexGrow msFlexNegative msFlexOrder msFlexPositive msFlexShrink msGridColumn msGridColumnSpan msGridRow msGridRowSpan WebkitAnimationIterationCount WebkitBoxFlex WebKitBoxFlexGroup WebkitBoxOrdinalGroup WebkitColumnCount WebkitColumns WebkitFlex WebkitFlexGrow WebkitFlexPositive WebkitFlexShrink WebkitLineClamp".split(
      " "
    )
  );
  function rr(e, t, l) {
    var a = t.indexOf("--") === 0;
    l == null || typeof l == "boolean" || l === "" ? a ? e.setProperty(t, "") : t === "float" ? e.cssFloat = "" : e[t] = "" : a ? e.setProperty(t, l) : typeof l != "number" || l === 0 || Em.has(t) ? t === "float" ? e.cssFloat = l : e[t] = ("" + l).trim() : e[t] = l + "px";
  }
  function fr(e, t, l) {
    if (t != null && typeof t != "object")
      throw Error(f(62));
    if (e = e.style, l != null) {
      for (var a in l)
        !l.hasOwnProperty(a) || t != null && t.hasOwnProperty(a) || (a.indexOf("--") === 0 ? e.setProperty(a, "") : a === "float" ? e.cssFloat = "" : e[a] = "");
      for (var u in t)
        a = t[u], t.hasOwnProperty(u) && l[u] !== a && rr(e, u, a);
    } else
      for (var i in t)
        t.hasOwnProperty(i) && rr(e, i, t[i]);
  }
  function Di(e) {
    if (e.indexOf("-") === -1) return !1;
    switch (e) {
      case "annotation-xml":
      case "color-profile":
      case "font-face":
      case "font-face-src":
      case "font-face-uri":
      case "font-face-format":
      case "font-face-name":
      case "missing-glyph":
        return !1;
      default:
        return !0;
    }
  }
  var Mm = /* @__PURE__ */ new Map([
    ["acceptCharset", "accept-charset"],
    ["htmlFor", "for"],
    ["httpEquiv", "http-equiv"],
    ["crossOrigin", "crossorigin"],
    ["accentHeight", "accent-height"],
    ["alignmentBaseline", "alignment-baseline"],
    ["arabicForm", "arabic-form"],
    ["baselineShift", "baseline-shift"],
    ["capHeight", "cap-height"],
    ["clipPath", "clip-path"],
    ["clipRule", "clip-rule"],
    ["colorInterpolation", "color-interpolation"],
    ["colorInterpolationFilters", "color-interpolation-filters"],
    ["colorProfile", "color-profile"],
    ["colorRendering", "color-rendering"],
    ["dominantBaseline", "dominant-baseline"],
    ["enableBackground", "enable-background"],
    ["fillOpacity", "fill-opacity"],
    ["fillRule", "fill-rule"],
    ["floodColor", "flood-color"],
    ["floodOpacity", "flood-opacity"],
    ["fontFamily", "font-family"],
    ["fontSize", "font-size"],
    ["fontSizeAdjust", "font-size-adjust"],
    ["fontStretch", "font-stretch"],
    ["fontStyle", "font-style"],
    ["fontVariant", "font-variant"],
    ["fontWeight", "font-weight"],
    ["glyphName", "glyph-name"],
    ["glyphOrientationHorizontal", "glyph-orientation-horizontal"],
    ["glyphOrientationVertical", "glyph-orientation-vertical"],
    ["horizAdvX", "horiz-adv-x"],
    ["horizOriginX", "horiz-origin-x"],
    ["imageRendering", "image-rendering"],
    ["letterSpacing", "letter-spacing"],
    ["lightingColor", "lighting-color"],
    ["markerEnd", "marker-end"],
    ["markerMid", "marker-mid"],
    ["markerStart", "marker-start"],
    ["overlinePosition", "overline-position"],
    ["overlineThickness", "overline-thickness"],
    ["paintOrder", "paint-order"],
    ["panose-1", "panose-1"],
    ["pointerEvents", "pointer-events"],
    ["renderingIntent", "rendering-intent"],
    ["shapeRendering", "shape-rendering"],
    ["stopColor", "stop-color"],
    ["stopOpacity", "stop-opacity"],
    ["strikethroughPosition", "strikethrough-position"],
    ["strikethroughThickness", "strikethrough-thickness"],
    ["strokeDasharray", "stroke-dasharray"],
    ["strokeDashoffset", "stroke-dashoffset"],
    ["strokeLinecap", "stroke-linecap"],
    ["strokeLinejoin", "stroke-linejoin"],
    ["strokeMiterlimit", "stroke-miterlimit"],
    ["strokeOpacity", "stroke-opacity"],
    ["strokeWidth", "stroke-width"],
    ["textAnchor", "text-anchor"],
    ["textDecoration", "text-decoration"],
    ["textRendering", "text-rendering"],
    ["transformOrigin", "transform-origin"],
    ["underlinePosition", "underline-position"],
    ["underlineThickness", "underline-thickness"],
    ["unicodeBidi", "unicode-bidi"],
    ["unicodeRange", "unicode-range"],
    ["unitsPerEm", "units-per-em"],
    ["vAlphabetic", "v-alphabetic"],
    ["vHanging", "v-hanging"],
    ["vIdeographic", "v-ideographic"],
    ["vMathematical", "v-mathematical"],
    ["vectorEffect", "vector-effect"],
    ["vertAdvY", "vert-adv-y"],
    ["vertOriginX", "vert-origin-x"],
    ["vertOriginY", "vert-origin-y"],
    ["wordSpacing", "word-spacing"],
    ["writingMode", "writing-mode"],
    ["xmlnsXlink", "xmlns:xlink"],
    ["xHeight", "x-height"]
  ]), zm = /^[\u0000-\u001F ]*j[\r\n\t]*a[\r\n\t]*v[\r\n\t]*a[\r\n\t]*s[\r\n\t]*c[\r\n\t]*r[\r\n\t]*i[\r\n\t]*p[\r\n\t]*t[\r\n\t]*:/i;
  function ru(e) {
    return zm.test("" + e) ? "javascript:throw new Error('React has blocked a javascript: URL as a security precaution.')" : e;
  }
  function tl() {
  }
  var Ri = null;
  function Ui(e) {
    return e = e.target || e.srcElement || window, e.correspondingUseElement && (e = e.correspondingUseElement), e.nodeType === 3 ? e.parentNode : e;
  }
  var Sa = null, Na = null;
  function or(e) {
    var t = ya(e);
    if (t && (e = t.stateNode)) {
      var l = e[pt] || null;
      e: switch (e = t.stateNode, t.type) {
        case "input":
          if (Ci(
            e,
            l.value,
            l.defaultValue,
            l.defaultValue,
            l.checked,
            l.defaultChecked,
            l.type,
            l.name
          ), t = l.name, l.type === "radio" && t != null) {
            for (l = e; l.parentNode; ) l = l.parentNode;
            for (l = l.querySelectorAll(
              'input[name="' + Ut(
                "" + t
              ) + '"][type="radio"]'
            ), t = 0; t < l.length; t++) {
              var a = l[t];
              if (a !== e && a.form === e.form) {
                var u = a[pt] || null;
                if (!u) throw Error(f(90));
                Ci(
                  a,
                  u.value,
                  u.defaultValue,
                  u.defaultValue,
                  u.checked,
                  u.defaultChecked,
                  u.type,
                  u.name
                );
              }
            }
            for (t = 0; t < l.length; t++)
              a = l[t], a.form === e.form && ur(a);
          }
          break e;
        case "textarea":
          sr(e, l.value, l.defaultValue);
          break e;
        case "select":
          t = l.value, t != null && ja(e, !!l.multiple, t, !1);
      }
    }
  }
  var Hi = !1;
  function dr(e, t, l) {
    if (Hi) return e(t, l);
    Hi = !0;
    try {
      var a = e(t);
      return a;
    } finally {
      if (Hi = !1, (Sa !== null || Na !== null) && (Fu(), Sa && (t = Sa, e = Na, Na = Sa = null, or(t), e)))
        for (t = 0; t < e.length; t++) or(e[t]);
    }
  }
  function on(e, t) {
    var l = e.stateNode;
    if (l === null) return null;
    var a = l[pt] || null;
    if (a === null) return null;
    l = a[t];
    e: switch (t) {
      case "onClick":
      case "onClickCapture":
      case "onDoubleClick":
      case "onDoubleClickCapture":
      case "onMouseDown":
      case "onMouseDownCapture":
      case "onMouseMove":
      case "onMouseMoveCapture":
      case "onMouseUp":
      case "onMouseUpCapture":
      case "onMouseEnter":
        (a = !a.disabled) || (e = e.type, a = !(e === "button" || e === "input" || e === "select" || e === "textarea")), e = !a;
        break e;
      default:
        e = !1;
    }
    if (e) return null;
    if (l && typeof l != "function")
      throw Error(
        f(231, t, typeof l)
      );
    return l;
  }
  var ll = !(typeof window > "u" || typeof window.document > "u" || typeof window.document.createElement > "u"), qi = !1;
  if (ll)
    try {
      var dn = {};
      Object.defineProperty(dn, "passive", {
        get: function() {
          qi = !0;
        }
      }), window.addEventListener("test", dn, dn), window.removeEventListener("test", dn, dn);
    } catch {
      qi = !1;
    }
  var jl = null, Bi = null, fu = null;
  function mr() {
    if (fu) return fu;
    var e, t = Bi, l = t.length, a, u = "value" in jl ? jl.value : jl.textContent, i = u.length;
    for (e = 0; e < l && t[e] === u[e]; e++) ;
    var c = l - e;
    for (a = 1; a <= c && t[l - a] === u[i - a]; a++) ;
    return fu = u.slice(e, 1 < a ? 1 - a : void 0);
  }
  function ou(e) {
    var t = e.keyCode;
    return "charCode" in e ? (e = e.charCode, e === 0 && t === 13 && (e = 13)) : e = t, e === 10 && (e = 13), 32 <= e || e === 13 ? e : 0;
  }
  function du() {
    return !0;
  }
  function hr() {
    return !1;
  }
  function bt(e) {
    function t(l, a, u, i, c) {
      this._reactName = l, this._targetInst = u, this.type = a, this.nativeEvent = i, this.target = c, this.currentTarget = null;
      for (var r in e)
        e.hasOwnProperty(r) && (l = e[r], this[r] = l ? l(i) : i[r]);
      return this.isDefaultPrevented = (i.defaultPrevented != null ? i.defaultPrevented : i.returnValue === !1) ? du : hr, this.isPropagationStopped = hr, this;
    }
    return y(t.prototype, {
      preventDefault: function() {
        this.defaultPrevented = !0;
        var l = this.nativeEvent;
        l && (l.preventDefault ? l.preventDefault() : typeof l.returnValue != "unknown" && (l.returnValue = !1), this.isDefaultPrevented = du);
      },
      stopPropagation: function() {
        var l = this.nativeEvent;
        l && (l.stopPropagation ? l.stopPropagation() : typeof l.cancelBubble != "unknown" && (l.cancelBubble = !0), this.isPropagationStopped = du);
      },
      persist: function() {
      },
      isPersistent: du
    }), t;
  }
  var Il = {
    eventPhase: 0,
    bubbles: 0,
    cancelable: 0,
    timeStamp: function(e) {
      return e.timeStamp || Date.now();
    },
    defaultPrevented: 0,
    isTrusted: 0
  }, mu = bt(Il), mn = y({}, Il, { view: 0, detail: 0 }), Tm = bt(mn), Yi, Gi, hn, hu = y({}, mn, {
    screenX: 0,
    screenY: 0,
    clientX: 0,
    clientY: 0,
    pageX: 0,
    pageY: 0,
    ctrlKey: 0,
    shiftKey: 0,
    altKey: 0,
    metaKey: 0,
    getModifierState: Li,
    button: 0,
    buttons: 0,
    relatedTarget: function(e) {
      return e.relatedTarget === void 0 ? e.fromElement === e.srcElement ? e.toElement : e.fromElement : e.relatedTarget;
    },
    movementX: function(e) {
      return "movementX" in e ? e.movementX : (e !== hn && (hn && e.type === "mousemove" ? (Yi = e.screenX - hn.screenX, Gi = e.screenY - hn.screenY) : Gi = Yi = 0, hn = e), Yi);
    },
    movementY: function(e) {
      return "movementY" in e ? e.movementY : Gi;
    }
  }), vr = bt(hu), Am = y({}, hu, { dataTransfer: 0 }), Cm = bt(Am), Om = y({}, mn, { relatedTarget: 0 }), Xi = bt(Om), Dm = y({}, Il, {
    animationName: 0,
    elapsedTime: 0,
    pseudoElement: 0
  }), Rm = bt(Dm), Um = y({}, Il, {
    clipboardData: function(e) {
      return "clipboardData" in e ? e.clipboardData : window.clipboardData;
    }
  }), Hm = bt(Um), qm = y({}, Il, { data: 0 }), pr = bt(qm), Bm = {
    Esc: "Escape",
    Spacebar: " ",
    Left: "ArrowLeft",
    Up: "ArrowUp",
    Right: "ArrowRight",
    Down: "ArrowDown",
    Del: "Delete",
    Win: "OS",
    Menu: "ContextMenu",
    Apps: "ContextMenu",
    Scroll: "ScrollLock",
    MozPrintableKey: "Unidentified"
  }, Ym = {
    8: "Backspace",
    9: "Tab",
    12: "Clear",
    13: "Enter",
    16: "Shift",
    17: "Control",
    18: "Alt",
    19: "Pause",
    20: "CapsLock",
    27: "Escape",
    32: " ",
    33: "PageUp",
    34: "PageDown",
    35: "End",
    36: "Home",
    37: "ArrowLeft",
    38: "ArrowUp",
    39: "ArrowRight",
    40: "ArrowDown",
    45: "Insert",
    46: "Delete",
    112: "F1",
    113: "F2",
    114: "F3",
    115: "F4",
    116: "F5",
    117: "F6",
    118: "F7",
    119: "F8",
    120: "F9",
    121: "F10",
    122: "F11",
    123: "F12",
    144: "NumLock",
    145: "ScrollLock",
    224: "Meta"
  }, Gm = {
    Alt: "altKey",
    Control: "ctrlKey",
    Meta: "metaKey",
    Shift: "shiftKey"
  };
  function Xm(e) {
    var t = this.nativeEvent;
    return t.getModifierState ? t.getModifierState(e) : (e = Gm[e]) ? !!t[e] : !1;
  }
  function Li() {
    return Xm;
  }
  var Lm = y({}, mn, {
    key: function(e) {
      if (e.key) {
        var t = Bm[e.key] || e.key;
        if (t !== "Unidentified") return t;
      }
      return e.type === "keypress" ? (e = ou(e), e === 13 ? "Enter" : String.fromCharCode(e)) : e.type === "keydown" || e.type === "keyup" ? Ym[e.keyCode] || "Unidentified" : "";
    },
    code: 0,
    location: 0,
    ctrlKey: 0,
    shiftKey: 0,
    altKey: 0,
    metaKey: 0,
    repeat: 0,
    locale: 0,
    getModifierState: Li,
    charCode: function(e) {
      return e.type === "keypress" ? ou(e) : 0;
    },
    keyCode: function(e) {
      return e.type === "keydown" || e.type === "keyup" ? e.keyCode : 0;
    },
    which: function(e) {
      return e.type === "keypress" ? ou(e) : e.type === "keydown" || e.type === "keyup" ? e.keyCode : 0;
    }
  }), Qm = bt(Lm), Zm = y({}, hu, {
    pointerId: 0,
    width: 0,
    height: 0,
    pressure: 0,
    tangentialPressure: 0,
    tiltX: 0,
    tiltY: 0,
    twist: 0,
    pointerType: 0,
    isPrimary: 0
  }), br = bt(Zm), wm = y({}, mn, {
    touches: 0,
    targetTouches: 0,
    changedTouches: 0,
    altKey: 0,
    metaKey: 0,
    ctrlKey: 0,
    shiftKey: 0,
    getModifierState: Li
  }), Vm = bt(wm), Km = y({}, Il, {
    propertyName: 0,
    elapsedTime: 0,
    pseudoElement: 0
  }), Jm = bt(Km), km = y({}, hu, {
    deltaX: function(e) {
      return "deltaX" in e ? e.deltaX : "wheelDeltaX" in e ? -e.wheelDeltaX : 0;
    },
    deltaY: function(e) {
      return "deltaY" in e ? e.deltaY : "wheelDeltaY" in e ? -e.wheelDeltaY : "wheelDelta" in e ? -e.wheelDelta : 0;
    },
    deltaZ: 0,
    deltaMode: 0
  }), $m = bt(km), Fm = y({}, Il, {
    newState: 0,
    oldState: 0
  }), Wm = bt(Fm), Im = [9, 13, 27, 32], Qi = ll && "CompositionEvent" in window, vn = null;
  ll && "documentMode" in document && (vn = document.documentMode);
  var Pm = ll && "TextEvent" in window && !vn, yr = ll && (!Qi || vn && 8 < vn && 11 >= vn), xr = " ", gr = !1;
  function jr(e, t) {
    switch (e) {
      case "keyup":
        return Im.indexOf(t.keyCode) !== -1;
      case "keydown":
        return t.keyCode !== 229;
      case "keypress":
      case "mousedown":
      case "focusout":
        return !0;
      default:
        return !1;
    }
  }
  function _r(e) {
    return e = e.detail, typeof e == "object" && "data" in e ? e.data : null;
  }
  var Ea = !1;
  function eh(e, t) {
    switch (e) {
      case "compositionend":
        return _r(t);
      case "keypress":
        return t.which !== 32 ? null : (gr = !0, xr);
      case "textInput":
        return e = t.data, e === xr && gr ? null : e;
      default:
        return null;
    }
  }
  function th(e, t) {
    if (Ea)
      return e === "compositionend" || !Qi && jr(e, t) ? (e = mr(), fu = Bi = jl = null, Ea = !1, e) : null;
    switch (e) {
      case "paste":
        return null;
      case "keypress":
        if (!(t.ctrlKey || t.altKey || t.metaKey) || t.ctrlKey && t.altKey) {
          if (t.char && 1 < t.char.length)
            return t.char;
          if (t.which) return String.fromCharCode(t.which);
        }
        return null;
      case "compositionend":
        return yr && t.locale !== "ko" ? null : t.data;
      default:
        return null;
    }
  }
  var lh = {
    color: !0,
    date: !0,
    datetime: !0,
    "datetime-local": !0,
    email: !0,
    month: !0,
    number: !0,
    password: !0,
    range: !0,
    search: !0,
    tel: !0,
    text: !0,
    time: !0,
    url: !0,
    week: !0
  };
  function Sr(e) {
    var t = e && e.nodeName && e.nodeName.toLowerCase();
    return t === "input" ? !!lh[e.type] : t === "textarea";
  }
  function Nr(e, t, l, a) {
    Sa ? Na ? Na.push(a) : Na = [a] : Sa = a, t = ai(t, "onChange"), 0 < t.length && (l = new mu(
      "onChange",
      "change",
      null,
      l,
      a
    ), e.push({ event: l, listeners: t }));
  }
  var pn = null, bn = null;
  function ah(e) {
    cd(e, 0);
  }
  function vu(e) {
    var t = fn(e);
    if (ur(t)) return e;
  }
  function Er(e, t) {
    if (e === "change") return t;
  }
  var Mr = !1;
  if (ll) {
    var Zi;
    if (ll) {
      var wi = "oninput" in document;
      if (!wi) {
        var zr = document.createElement("div");
        zr.setAttribute("oninput", "return;"), wi = typeof zr.oninput == "function";
      }
      Zi = wi;
    } else Zi = !1;
    Mr = Zi && (!document.documentMode || 9 < document.documentMode);
  }
  function Tr() {
    pn && (pn.detachEvent("onpropertychange", Ar), bn = pn = null);
  }
  function Ar(e) {
    if (e.propertyName === "value" && vu(bn)) {
      var t = [];
      Nr(
        t,
        bn,
        e,
        Ui(e)
      ), dr(ah, t);
    }
  }
  function nh(e, t, l) {
    e === "focusin" ? (Tr(), pn = t, bn = l, pn.attachEvent("onpropertychange", Ar)) : e === "focusout" && Tr();
  }
  function uh(e) {
    if (e === "selectionchange" || e === "keyup" || e === "keydown")
      return vu(bn);
  }
  function ih(e, t) {
    if (e === "click") return vu(t);
  }
  function sh(e, t) {
    if (e === "input" || e === "change")
      return vu(t);
  }
  function ch(e, t) {
    return e === t && (e !== 0 || 1 / e === 1 / t) || e !== e && t !== t;
  }
  var Et = typeof Object.is == "function" ? Object.is : ch;
  function yn(e, t) {
    if (Et(e, t)) return !0;
    if (typeof e != "object" || e === null || typeof t != "object" || t === null)
      return !1;
    var l = Object.keys(e), a = Object.keys(t);
    if (l.length !== a.length) return !1;
    for (a = 0; a < l.length; a++) {
      var u = l[a];
      if (!Dt.call(t, u) || !Et(e[u], t[u]))
        return !1;
    }
    return !0;
  }
  function Cr(e) {
    for (; e && e.firstChild; ) e = e.firstChild;
    return e;
  }
  function Or(e, t) {
    var l = Cr(e);
    e = 0;
    for (var a; l; ) {
      if (l.nodeType === 3) {
        if (a = e + l.textContent.length, e <= t && a >= t)
          return { node: l, offset: t - e };
        e = a;
      }
      e: {
        for (; l; ) {
          if (l.nextSibling) {
            l = l.nextSibling;
            break e;
          }
          l = l.parentNode;
        }
        l = void 0;
      }
      l = Cr(l);
    }
  }
  function Dr(e, t) {
    return e && t ? e === t ? !0 : e && e.nodeType === 3 ? !1 : t && t.nodeType === 3 ? Dr(e, t.parentNode) : "contains" in e ? e.contains(t) : e.compareDocumentPosition ? !!(e.compareDocumentPosition(t) & 16) : !1 : !1;
  }
  function Rr(e) {
    e = e != null && e.ownerDocument != null && e.ownerDocument.defaultView != null ? e.ownerDocument.defaultView : window;
    for (var t = cu(e.document); t instanceof e.HTMLIFrameElement; ) {
      try {
        var l = typeof t.contentWindow.location.href == "string";
      } catch {
        l = !1;
      }
      if (l) e = t.contentWindow;
      else break;
      t = cu(e.document);
    }
    return t;
  }
  function Vi(e) {
    var t = e && e.nodeName && e.nodeName.toLowerCase();
    return t && (t === "input" && (e.type === "text" || e.type === "search" || e.type === "tel" || e.type === "url" || e.type === "password") || t === "textarea" || e.contentEditable === "true");
  }
  var rh = ll && "documentMode" in document && 11 >= document.documentMode, Ma = null, Ki = null, xn = null, Ji = !1;
  function Ur(e, t, l) {
    var a = l.window === l ? l.document : l.nodeType === 9 ? l : l.ownerDocument;
    Ji || Ma == null || Ma !== cu(a) || (a = Ma, "selectionStart" in a && Vi(a) ? a = { start: a.selectionStart, end: a.selectionEnd } : (a = (a.ownerDocument && a.ownerDocument.defaultView || window).getSelection(), a = {
      anchorNode: a.anchorNode,
      anchorOffset: a.anchorOffset,
      focusNode: a.focusNode,
      focusOffset: a.focusOffset
    }), xn && yn(xn, a) || (xn = a, a = ai(Ki, "onSelect"), 0 < a.length && (t = new mu(
      "onSelect",
      "select",
      null,
      t,
      l
    ), e.push({ event: t, listeners: a }), t.target = Ma)));
  }
  function Pl(e, t) {
    var l = {};
    return l[e.toLowerCase()] = t.toLowerCase(), l["Webkit" + e] = "webkit" + t, l["Moz" + e] = "moz" + t, l;
  }
  var za = {
    animationend: Pl("Animation", "AnimationEnd"),
    animationiteration: Pl("Animation", "AnimationIteration"),
    animationstart: Pl("Animation", "AnimationStart"),
    transitionrun: Pl("Transition", "TransitionRun"),
    transitionstart: Pl("Transition", "TransitionStart"),
    transitioncancel: Pl("Transition", "TransitionCancel"),
    transitionend: Pl("Transition", "TransitionEnd")
  }, ki = {}, Hr = {};
  ll && (Hr = document.createElement("div").style, "AnimationEvent" in window || (delete za.animationend.animation, delete za.animationiteration.animation, delete za.animationstart.animation), "TransitionEvent" in window || delete za.transitionend.transition);
  function ea(e) {
    if (ki[e]) return ki[e];
    if (!za[e]) return e;
    var t = za[e], l;
    for (l in t)
      if (t.hasOwnProperty(l) && l in Hr)
        return ki[e] = t[l];
    return e;
  }
  var qr = ea("animationend"), Br = ea("animationiteration"), Yr = ea("animationstart"), fh = ea("transitionrun"), oh = ea("transitionstart"), dh = ea("transitioncancel"), Gr = ea("transitionend"), Xr = /* @__PURE__ */ new Map(), $i = "abort auxClick beforeToggle cancel canPlay canPlayThrough click close contextMenu copy cut drag dragEnd dragEnter dragExit dragLeave dragOver dragStart drop durationChange emptied encrypted ended error gotPointerCapture input invalid keyDown keyPress keyUp load loadedData loadedMetadata loadStart lostPointerCapture mouseDown mouseMove mouseOut mouseOver mouseUp paste pause play playing pointerCancel pointerDown pointerMove pointerOut pointerOver pointerUp progress rateChange reset resize seeked seeking stalled submit suspend timeUpdate touchCancel touchEnd touchStart volumeChange scroll toggle touchMove waiting wheel".split(
    " "
  );
  $i.push("scrollEnd");
  function wt(e, t) {
    Xr.set(e, t), Wl(t, [e]);
  }
  var pu = typeof reportError == "function" ? reportError : function(e) {
    if (typeof window == "object" && typeof window.ErrorEvent == "function") {
      var t = new window.ErrorEvent("error", {
        bubbles: !0,
        cancelable: !0,
        message: typeof e == "object" && e !== null && typeof e.message == "string" ? String(e.message) : String(e),
        error: e
      });
      if (!window.dispatchEvent(t)) return;
    } else if (typeof process == "object" && typeof process.emit == "function") {
      process.emit("uncaughtException", e);
      return;
    }
    console.error(e);
  }, Ht = [], Ta = 0, Fi = 0;
  function bu() {
    for (var e = Ta, t = Fi = Ta = 0; t < e; ) {
      var l = Ht[t];
      Ht[t++] = null;
      var a = Ht[t];
      Ht[t++] = null;
      var u = Ht[t];
      Ht[t++] = null;
      var i = Ht[t];
      if (Ht[t++] = null, a !== null && u !== null) {
        var c = a.pending;
        c === null ? u.next = u : (u.next = c.next, c.next = u), a.pending = u;
      }
      i !== 0 && Lr(l, u, i);
    }
  }
  function yu(e, t, l, a) {
    Ht[Ta++] = e, Ht[Ta++] = t, Ht[Ta++] = l, Ht[Ta++] = a, Fi |= a, e.lanes |= a, e = e.alternate, e !== null && (e.lanes |= a);
  }
  function Wi(e, t, l, a) {
    return yu(e, t, l, a), xu(e);
  }
  function ta(e, t) {
    return yu(e, null, null, t), xu(e);
  }
  function Lr(e, t, l) {
    e.lanes |= l;
    var a = e.alternate;
    a !== null && (a.lanes |= l);
    for (var u = !1, i = e.return; i !== null; )
      i.childLanes |= l, a = i.alternate, a !== null && (a.childLanes |= l), i.tag === 22 && (e = i.stateNode, e === null || e._visibility & 1 || (u = !0)), e = i, i = i.return;
    return e.tag === 3 ? (i = e.stateNode, u && t !== null && (u = 31 - Nt(l), e = i.hiddenUpdates, a = e[u], a === null ? e[u] = [t] : a.push(t), t.lane = l | 536870912), i) : null;
  }
  function xu(e) {
    if (50 < Ln)
      throw Ln = 0, ic = null, Error(f(185));
    for (var t = e.return; t !== null; )
      e = t, t = e.return;
    return e.tag === 3 ? e.stateNode : null;
  }
  var Aa = {};
  function mh(e, t, l, a) {
    this.tag = e, this.key = l, this.sibling = this.child = this.return = this.stateNode = this.type = this.elementType = null, this.index = 0, this.refCleanup = this.ref = null, this.pendingProps = t, this.dependencies = this.memoizedState = this.updateQueue = this.memoizedProps = null, this.mode = a, this.subtreeFlags = this.flags = 0, this.deletions = null, this.childLanes = this.lanes = 0, this.alternate = null;
  }
  function Mt(e, t, l, a) {
    return new mh(e, t, l, a);
  }
  function Ii(e) {
    return e = e.prototype, !(!e || !e.isReactComponent);
  }
  function al(e, t) {
    var l = e.alternate;
    return l === null ? (l = Mt(
      e.tag,
      t,
      e.key,
      e.mode
    ), l.elementType = e.elementType, l.type = e.type, l.stateNode = e.stateNode, l.alternate = e, e.alternate = l) : (l.pendingProps = t, l.type = e.type, l.flags = 0, l.subtreeFlags = 0, l.deletions = null), l.flags = e.flags & 65011712, l.childLanes = e.childLanes, l.lanes = e.lanes, l.child = e.child, l.memoizedProps = e.memoizedProps, l.memoizedState = e.memoizedState, l.updateQueue = e.updateQueue, t = e.dependencies, l.dependencies = t === null ? null : { lanes: t.lanes, firstContext: t.firstContext }, l.sibling = e.sibling, l.index = e.index, l.ref = e.ref, l.refCleanup = e.refCleanup, l;
  }
  function Qr(e, t) {
    e.flags &= 65011714;
    var l = e.alternate;
    return l === null ? (e.childLanes = 0, e.lanes = t, e.child = null, e.subtreeFlags = 0, e.memoizedProps = null, e.memoizedState = null, e.updateQueue = null, e.dependencies = null, e.stateNode = null) : (e.childLanes = l.childLanes, e.lanes = l.lanes, e.child = l.child, e.subtreeFlags = 0, e.deletions = null, e.memoizedProps = l.memoizedProps, e.memoizedState = l.memoizedState, e.updateQueue = l.updateQueue, e.type = l.type, t = l.dependencies, e.dependencies = t === null ? null : {
      lanes: t.lanes,
      firstContext: t.firstContext
    }), e;
  }
  function gu(e, t, l, a, u, i) {
    var c = 0;
    if (a = e, typeof e == "function") Ii(e) && (c = 1);
    else if (typeof e == "string")
      c = y0(
        e,
        l,
        G.current
      ) ? 26 : e === "html" || e === "head" || e === "body" ? 27 : 5;
    else
      e: switch (e) {
        case ee:
          return e = Mt(31, l, t, u), e.elementType = ee, e.lanes = i, e;
        case I:
          return la(l.children, u, i, t);
        case ie:
          c = 8, u |= 24;
          break;
        case J:
          return e = Mt(12, l, t, u | 2), e.elementType = J, e.lanes = i, e;
        case te:
          return e = Mt(13, l, t, u), e.elementType = te, e.lanes = i, e;
        case le:
          return e = Mt(19, l, t, u), e.elementType = le, e.lanes = i, e;
        default:
          if (typeof e == "object" && e !== null)
            switch (e.$$typeof) {
              case V:
                c = 10;
                break e;
              case ue:
                c = 9;
                break e;
              case W:
                c = 11;
                break e;
              case P:
                c = 14;
                break e;
              case F:
                c = 16, a = null;
                break e;
            }
          c = 29, l = Error(
            f(130, e === null ? "null" : typeof e, "")
          ), a = null;
      }
    return t = Mt(c, l, t, u), t.elementType = e, t.type = a, t.lanes = i, t;
  }
  function la(e, t, l, a) {
    return e = Mt(7, e, a, t), e.lanes = l, e;
  }
  function Pi(e, t, l) {
    return e = Mt(6, e, null, t), e.lanes = l, e;
  }
  function Zr(e) {
    var t = Mt(18, null, null, 0);
    return t.stateNode = e, t;
  }
  function es(e, t, l) {
    return t = Mt(
      4,
      e.children !== null ? e.children : [],
      e.key,
      t
    ), t.lanes = l, t.stateNode = {
      containerInfo: e.containerInfo,
      pendingChildren: null,
      implementation: e.implementation
    }, t;
  }
  var wr = /* @__PURE__ */ new WeakMap();
  function qt(e, t) {
    if (typeof e == "object" && e !== null) {
      var l = wr.get(e);
      return l !== void 0 ? l : (t = {
        value: e,
        source: t,
        stack: Re(t)
      }, wr.set(e, t), t);
    }
    return {
      value: e,
      source: t,
      stack: Re(t)
    };
  }
  var Ca = [], Oa = 0, ju = null, gn = 0, Bt = [], Yt = 0, _l = null, kt = 1, $t = "";
  function nl(e, t) {
    Ca[Oa++] = gn, Ca[Oa++] = ju, ju = e, gn = t;
  }
  function Vr(e, t, l) {
    Bt[Yt++] = kt, Bt[Yt++] = $t, Bt[Yt++] = _l, _l = e;
    var a = kt;
    e = $t;
    var u = 32 - Nt(a) - 1;
    a &= ~(1 << u), l += 1;
    var i = 32 - Nt(t) + u;
    if (30 < i) {
      var c = u - u % 5;
      i = (a & (1 << c) - 1).toString(32), a >>= c, u -= c, kt = 1 << 32 - Nt(t) + u | l << u | a, $t = i + e;
    } else
      kt = 1 << i | l << u | a, $t = e;
  }
  function ts(e) {
    e.return !== null && (nl(e, 1), Vr(e, 1, 0));
  }
  function ls(e) {
    for (; e === ju; )
      ju = Ca[--Oa], Ca[Oa] = null, gn = Ca[--Oa], Ca[Oa] = null;
    for (; e === _l; )
      _l = Bt[--Yt], Bt[Yt] = null, $t = Bt[--Yt], Bt[Yt] = null, kt = Bt[--Yt], Bt[Yt] = null;
  }
  function Kr(e, t) {
    Bt[Yt++] = kt, Bt[Yt++] = $t, Bt[Yt++] = _l, kt = t.id, $t = t.overflow, _l = e;
  }
  var it = null, Le = null, Te = !1, Sl = null, Gt = !1, as = Error(f(519));
  function Nl(e) {
    var t = Error(
      f(
        418,
        1 < arguments.length && arguments[1] !== void 0 && arguments[1] ? "text" : "HTML",
        ""
      )
    );
    throw jn(qt(t, e)), as;
  }
  function Jr(e) {
    var t = e.stateNode, l = e.type, a = e.memoizedProps;
    switch (t[ut] = e, t[pt] = a, l) {
      case "dialog":
        Ee("cancel", t), Ee("close", t);
        break;
      case "iframe":
      case "object":
      case "embed":
        Ee("load", t);
        break;
      case "video":
      case "audio":
        for (l = 0; l < Zn.length; l++)
          Ee(Zn[l], t);
        break;
      case "source":
        Ee("error", t);
        break;
      case "img":
      case "image":
      case "link":
        Ee("error", t), Ee("load", t);
        break;
      case "details":
        Ee("toggle", t);
        break;
      case "input":
        Ee("invalid", t), ir(
          t,
          a.value,
          a.defaultValue,
          a.checked,
          a.defaultChecked,
          a.type,
          a.name,
          !0
        );
        break;
      case "select":
        Ee("invalid", t);
        break;
      case "textarea":
        Ee("invalid", t), cr(t, a.value, a.defaultValue, a.children);
    }
    l = a.children, typeof l != "string" && typeof l != "number" && typeof l != "bigint" || t.textContent === "" + l || a.suppressHydrationWarning === !0 || dd(t.textContent, l) ? (a.popover != null && (Ee("beforetoggle", t), Ee("toggle", t)), a.onScroll != null && Ee("scroll", t), a.onScrollEnd != null && Ee("scrollend", t), a.onClick != null && (t.onclick = tl), t = !0) : t = !1, t || Nl(e, !0);
  }
  function kr(e) {
    for (it = e.return; it; )
      switch (it.tag) {
        case 5:
        case 31:
        case 13:
          Gt = !1;
          return;
        case 27:
        case 3:
          Gt = !0;
          return;
        default:
          it = it.return;
      }
  }
  function Da(e) {
    if (e !== it) return !1;
    if (!Te) return kr(e), Te = !0, !1;
    var t = e.tag, l;
    if ((l = t !== 3 && t !== 27) && ((l = t === 5) && (l = e.type, l = !(l !== "form" && l !== "button") || jc(e.type, e.memoizedProps)), l = !l), l && Le && Nl(e), kr(e), t === 13) {
      if (e = e.memoizedState, e = e !== null ? e.dehydrated : null, !e) throw Error(f(317));
      Le = jd(e);
    } else if (t === 31) {
      if (e = e.memoizedState, e = e !== null ? e.dehydrated : null, !e) throw Error(f(317));
      Le = jd(e);
    } else
      t === 27 ? (t = Le, Yl(e.type) ? (e = Mc, Mc = null, Le = e) : Le = t) : Le = it ? Lt(e.stateNode.nextSibling) : null;
    return !0;
  }
  function aa() {
    Le = it = null, Te = !1;
  }
  function ns() {
    var e = Sl;
    return e !== null && (jt === null ? jt = e : jt.push.apply(
      jt,
      e
    ), Sl = null), e;
  }
  function jn(e) {
    Sl === null ? Sl = [e] : Sl.push(e);
  }
  var us = m(null), na = null, ul = null;
  function El(e, t, l) {
    D(us, t._currentValue), t._currentValue = l;
  }
  function il(e) {
    e._currentValue = us.current, o(us);
  }
  function is(e, t, l) {
    for (; e !== null; ) {
      var a = e.alternate;
      if ((e.childLanes & t) !== t ? (e.childLanes |= t, a !== null && (a.childLanes |= t)) : a !== null && (a.childLanes & t) !== t && (a.childLanes |= t), e === l) break;
      e = e.return;
    }
  }
  function ss(e, t, l, a) {
    var u = e.child;
    for (u !== null && (u.return = e); u !== null; ) {
      var i = u.dependencies;
      if (i !== null) {
        var c = u.child;
        i = i.firstContext;
        e: for (; i !== null; ) {
          var r = i;
          i = u;
          for (var v = 0; v < t.length; v++)
            if (r.context === t[v]) {
              i.lanes |= l, r = i.alternate, r !== null && (r.lanes |= l), is(
                i.return,
                l,
                e
              ), a || (c = null);
              break e;
            }
          i = r.next;
        }
      } else if (u.tag === 18) {
        if (c = u.return, c === null) throw Error(f(341));
        c.lanes |= l, i = c.alternate, i !== null && (i.lanes |= l), is(c, l, e), c = null;
      } else c = u.child;
      if (c !== null) c.return = u;
      else
        for (c = u; c !== null; ) {
          if (c === e) {
            c = null;
            break;
          }
          if (u = c.sibling, u !== null) {
            u.return = c.return, c = u;
            break;
          }
          c = c.return;
        }
      u = c;
    }
  }
  function Ra(e, t, l, a) {
    e = null;
    for (var u = t, i = !1; u !== null; ) {
      if (!i) {
        if ((u.flags & 524288) !== 0) i = !0;
        else if ((u.flags & 262144) !== 0) break;
      }
      if (u.tag === 10) {
        var c = u.alternate;
        if (c === null) throw Error(f(387));
        if (c = c.memoizedProps, c !== null) {
          var r = u.type;
          Et(u.pendingProps.value, c.value) || (e !== null ? e.push(r) : e = [r]);
        }
      } else if (u === de.current) {
        if (c = u.alternate, c === null) throw Error(f(387));
        c.memoizedState.memoizedState !== u.memoizedState.memoizedState && (e !== null ? e.push(kn) : e = [kn]);
      }
      u = u.return;
    }
    e !== null && ss(
      t,
      e,
      l,
      a
    ), t.flags |= 262144;
  }
  function _u(e) {
    for (e = e.firstContext; e !== null; ) {
      if (!Et(
        e.context._currentValue,
        e.memoizedValue
      ))
        return !0;
      e = e.next;
    }
    return !1;
  }
  function ua(e) {
    na = e, ul = null, e = e.dependencies, e !== null && (e.firstContext = null);
  }
  function st(e) {
    return $r(na, e);
  }
  function Su(e, t) {
    return na === null && ua(e), $r(e, t);
  }
  function $r(e, t) {
    var l = t._currentValue;
    if (t = { context: t, memoizedValue: l, next: null }, ul === null) {
      if (e === null) throw Error(f(308));
      ul = t, e.dependencies = { lanes: 0, firstContext: t }, e.flags |= 524288;
    } else ul = ul.next = t;
    return l;
  }
  var hh = typeof AbortController < "u" ? AbortController : function() {
    var e = [], t = this.signal = {
      aborted: !1,
      addEventListener: function(l, a) {
        e.push(a);
      }
    };
    this.abort = function() {
      t.aborted = !0, e.forEach(function(l) {
        return l();
      });
    };
  }, vh = s.unstable_scheduleCallback, ph = s.unstable_NormalPriority, Fe = {
    $$typeof: V,
    Consumer: null,
    Provider: null,
    _currentValue: null,
    _currentValue2: null,
    _threadCount: 0
  };
  function cs() {
    return {
      controller: new hh(),
      data: /* @__PURE__ */ new Map(),
      refCount: 0
    };
  }
  function _n(e) {
    e.refCount--, e.refCount === 0 && vh(ph, function() {
      e.controller.abort();
    });
  }
  var Sn = null, rs = 0, Ua = 0, Ha = null;
  function bh(e, t) {
    if (Sn === null) {
      var l = Sn = [];
      rs = 0, Ua = dc(), Ha = {
        status: "pending",
        value: void 0,
        then: function(a) {
          l.push(a);
        }
      };
    }
    return rs++, t.then(Fr, Fr), t;
  }
  function Fr() {
    if (--rs === 0 && Sn !== null) {
      Ha !== null && (Ha.status = "fulfilled");
      var e = Sn;
      Sn = null, Ua = 0, Ha = null;
      for (var t = 0; t < e.length; t++) (0, e[t])();
    }
  }
  function yh(e, t) {
    var l = [], a = {
      status: "pending",
      value: null,
      reason: null,
      then: function(u) {
        l.push(u);
      }
    };
    return e.then(
      function() {
        a.status = "fulfilled", a.value = t;
        for (var u = 0; u < l.length; u++) (0, l[u])(t);
      },
      function(u) {
        for (a.status = "rejected", a.reason = u, u = 0; u < l.length; u++)
          (0, l[u])(void 0);
      }
    ), a;
  }
  var Wr = S.S;
  S.S = function(e, t) {
    qo = mt(), typeof t == "object" && t !== null && typeof t.then == "function" && bh(e, t), Wr !== null && Wr(e, t);
  };
  var ia = m(null);
  function fs() {
    var e = ia.current;
    return e !== null ? e : Xe.pooledCache;
  }
  function Nu(e, t) {
    t === null ? D(ia, ia.current) : D(ia, t.pool);
  }
  function Ir() {
    var e = fs();
    return e === null ? null : { parent: Fe._currentValue, pool: e };
  }
  var qa = Error(f(460)), os = Error(f(474)), Eu = Error(f(542)), Mu = { then: function() {
  } };
  function Pr(e) {
    return e = e.status, e === "fulfilled" || e === "rejected";
  }
  function ef(e, t, l) {
    switch (l = e[l], l === void 0 ? e.push(t) : l !== t && (t.then(tl, tl), t = l), t.status) {
      case "fulfilled":
        return t.value;
      case "rejected":
        throw e = t.reason, lf(e), e;
      default:
        if (typeof t.status == "string") t.then(tl, tl);
        else {
          if (e = Xe, e !== null && 100 < e.shellSuspendCounter)
            throw Error(f(482));
          e = t, e.status = "pending", e.then(
            function(a) {
              if (t.status === "pending") {
                var u = t;
                u.status = "fulfilled", u.value = a;
              }
            },
            function(a) {
              if (t.status === "pending") {
                var u = t;
                u.status = "rejected", u.reason = a;
              }
            }
          );
        }
        switch (t.status) {
          case "fulfilled":
            return t.value;
          case "rejected":
            throw e = t.reason, lf(e), e;
        }
        throw ca = t, qa;
    }
  }
  function sa(e) {
    try {
      var t = e._init;
      return t(e._payload);
    } catch (l) {
      throw l !== null && typeof l == "object" && typeof l.then == "function" ? (ca = l, qa) : l;
    }
  }
  var ca = null;
  function tf() {
    if (ca === null) throw Error(f(459));
    var e = ca;
    return ca = null, e;
  }
  function lf(e) {
    if (e === qa || e === Eu)
      throw Error(f(483));
  }
  var Ba = null, Nn = 0;
  function zu(e) {
    var t = Nn;
    return Nn += 1, Ba === null && (Ba = []), ef(Ba, e, t);
  }
  function En(e, t) {
    t = t.props.ref, e.ref = t !== void 0 ? t : null;
  }
  function Tu(e, t) {
    throw t.$$typeof === p ? Error(f(525)) : (e = Object.prototype.toString.call(t), Error(
      f(
        31,
        e === "[object Object]" ? "object with keys {" + Object.keys(t).join(", ") + "}" : e
      )
    ));
  }
  function af(e) {
    function t(N, g) {
      if (e) {
        var A = N.deletions;
        A === null ? (N.deletions = [g], N.flags |= 16) : A.push(g);
      }
    }
    function l(N, g) {
      if (!e) return null;
      for (; g !== null; )
        t(N, g), g = g.sibling;
      return null;
    }
    function a(N) {
      for (var g = /* @__PURE__ */ new Map(); N !== null; )
        N.key !== null ? g.set(N.key, N) : g.set(N.index, N), N = N.sibling;
      return g;
    }
    function u(N, g) {
      return N = al(N, g), N.index = 0, N.sibling = null, N;
    }
    function i(N, g, A) {
      return N.index = A, e ? (A = N.alternate, A !== null ? (A = A.index, A < g ? (N.flags |= 67108866, g) : A) : (N.flags |= 67108866, g)) : (N.flags |= 1048576, g);
    }
    function c(N) {
      return e && N.alternate === null && (N.flags |= 67108866), N;
    }
    function r(N, g, A, K) {
      return g === null || g.tag !== 6 ? (g = Pi(A, N.mode, K), g.return = N, g) : (g = u(g, A), g.return = N, g);
    }
    function v(N, g, A, K) {
      var oe = A.type;
      return oe === I ? Q(
        N,
        g,
        A.props.children,
        K,
        A.key
      ) : g !== null && (g.elementType === oe || typeof oe == "object" && oe !== null && oe.$$typeof === F && sa(oe) === g.type) ? (g = u(g, A.props), En(g, A), g.return = N, g) : (g = gu(
        A.type,
        A.key,
        A.props,
        null,
        N.mode,
        K
      ), En(g, A), g.return = N, g);
    }
    function C(N, g, A, K) {
      return g === null || g.tag !== 4 || g.stateNode.containerInfo !== A.containerInfo || g.stateNode.implementation !== A.implementation ? (g = es(A, N.mode, K), g.return = N, g) : (g = u(g, A.children || []), g.return = N, g);
    }
    function Q(N, g, A, K, oe) {
      return g === null || g.tag !== 7 ? (g = la(
        A,
        N.mode,
        K,
        oe
      ), g.return = N, g) : (g = u(g, A), g.return = N, g);
    }
    function $(N, g, A) {
      if (typeof g == "string" && g !== "" || typeof g == "number" || typeof g == "bigint")
        return g = Pi(
          "" + g,
          N.mode,
          A
        ), g.return = N, g;
      if (typeof g == "object" && g !== null) {
        switch (g.$$typeof) {
          case Y:
            return A = gu(
              g.type,
              g.key,
              g.props,
              null,
              N.mode,
              A
            ), En(A, g), A.return = N, A;
          case X:
            return g = es(
              g,
              N.mode,
              A
            ), g.return = N, g;
          case F:
            return g = sa(g), $(N, g, A);
        }
        if (L(g) || h(g))
          return g = la(
            g,
            N.mode,
            A,
            null
          ), g.return = N, g;
        if (typeof g.then == "function")
          return $(N, zu(g), A);
        if (g.$$typeof === V)
          return $(
            N,
            Su(N, g),
            A
          );
        Tu(N, g);
      }
      return null;
    }
    function R(N, g, A, K) {
      var oe = g !== null ? g.key : null;
      if (typeof A == "string" && A !== "" || typeof A == "number" || typeof A == "bigint")
        return oe !== null ? null : r(N, g, "" + A, K);
      if (typeof A == "object" && A !== null) {
        switch (A.$$typeof) {
          case Y:
            return A.key === oe ? v(N, g, A, K) : null;
          case X:
            return A.key === oe ? C(N, g, A, K) : null;
          case F:
            return A = sa(A), R(N, g, A, K);
        }
        if (L(A) || h(A))
          return oe !== null ? null : Q(N, g, A, K, null);
        if (typeof A.then == "function")
          return R(
            N,
            g,
            zu(A),
            K
          );
        if (A.$$typeof === V)
          return R(
            N,
            g,
            Su(N, A),
            K
          );
        Tu(N, A);
      }
      return null;
    }
    function B(N, g, A, K, oe) {
      if (typeof K == "string" && K !== "" || typeof K == "number" || typeof K == "bigint")
        return N = N.get(A) || null, r(g, N, "" + K, oe);
      if (typeof K == "object" && K !== null) {
        switch (K.$$typeof) {
          case Y:
            return N = N.get(
              K.key === null ? A : K.key
            ) || null, v(g, N, K, oe);
          case X:
            return N = N.get(
              K.key === null ? A : K.key
            ) || null, C(g, N, K, oe);
          case F:
            return K = sa(K), B(
              N,
              g,
              A,
              K,
              oe
            );
        }
        if (L(K) || h(K))
          return N = N.get(A) || null, Q(g, N, K, oe, null);
        if (typeof K.then == "function")
          return B(
            N,
            g,
            A,
            zu(K),
            oe
          );
        if (K.$$typeof === V)
          return B(
            N,
            g,
            A,
            Su(g, K),
            oe
          );
        Tu(g, K);
      }
      return null;
    }
    function se(N, g, A, K) {
      for (var oe = null, Ae = null, re = g, je = g = 0, ze = null; re !== null && je < A.length; je++) {
        re.index > je ? (ze = re, re = null) : ze = re.sibling;
        var Ce = R(
          N,
          re,
          A[je],
          K
        );
        if (Ce === null) {
          re === null && (re = ze);
          break;
        }
        e && re && Ce.alternate === null && t(N, re), g = i(Ce, g, je), Ae === null ? oe = Ce : Ae.sibling = Ce, Ae = Ce, re = ze;
      }
      if (je === A.length)
        return l(N, re), Te && nl(N, je), oe;
      if (re === null) {
        for (; je < A.length; je++)
          re = $(N, A[je], K), re !== null && (g = i(
            re,
            g,
            je
          ), Ae === null ? oe = re : Ae.sibling = re, Ae = re);
        return Te && nl(N, je), oe;
      }
      for (re = a(re); je < A.length; je++)
        ze = B(
          re,
          N,
          je,
          A[je],
          K
        ), ze !== null && (e && ze.alternate !== null && re.delete(
          ze.key === null ? je : ze.key
        ), g = i(
          ze,
          g,
          je
        ), Ae === null ? oe = ze : Ae.sibling = ze, Ae = ze);
      return e && re.forEach(function(Zl) {
        return t(N, Zl);
      }), Te && nl(N, je), oe;
    }
    function he(N, g, A, K) {
      if (A == null) throw Error(f(151));
      for (var oe = null, Ae = null, re = g, je = g = 0, ze = null, Ce = A.next(); re !== null && !Ce.done; je++, Ce = A.next()) {
        re.index > je ? (ze = re, re = null) : ze = re.sibling;
        var Zl = R(N, re, Ce.value, K);
        if (Zl === null) {
          re === null && (re = ze);
          break;
        }
        e && re && Zl.alternate === null && t(N, re), g = i(Zl, g, je), Ae === null ? oe = Zl : Ae.sibling = Zl, Ae = Zl, re = ze;
      }
      if (Ce.done)
        return l(N, re), Te && nl(N, je), oe;
      if (re === null) {
        for (; !Ce.done; je++, Ce = A.next())
          Ce = $(N, Ce.value, K), Ce !== null && (g = i(Ce, g, je), Ae === null ? oe = Ce : Ae.sibling = Ce, Ae = Ce);
        return Te && nl(N, je), oe;
      }
      for (re = a(re); !Ce.done; je++, Ce = A.next())
        Ce = B(re, N, je, Ce.value, K), Ce !== null && (e && Ce.alternate !== null && re.delete(Ce.key === null ? je : Ce.key), g = i(Ce, g, je), Ae === null ? oe = Ce : Ae.sibling = Ce, Ae = Ce);
      return e && re.forEach(function(A0) {
        return t(N, A0);
      }), Te && nl(N, je), oe;
    }
    function Ge(N, g, A, K) {
      if (typeof A == "object" && A !== null && A.type === I && A.key === null && (A = A.props.children), typeof A == "object" && A !== null) {
        switch (A.$$typeof) {
          case Y:
            e: {
              for (var oe = A.key; g !== null; ) {
                if (g.key === oe) {
                  if (oe = A.type, oe === I) {
                    if (g.tag === 7) {
                      l(
                        N,
                        g.sibling
                      ), K = u(
                        g,
                        A.props.children
                      ), K.return = N, N = K;
                      break e;
                    }
                  } else if (g.elementType === oe || typeof oe == "object" && oe !== null && oe.$$typeof === F && sa(oe) === g.type) {
                    l(
                      N,
                      g.sibling
                    ), K = u(g, A.props), En(K, A), K.return = N, N = K;
                    break e;
                  }
                  l(N, g);
                  break;
                } else t(N, g);
                g = g.sibling;
              }
              A.type === I ? (K = la(
                A.props.children,
                N.mode,
                K,
                A.key
              ), K.return = N, N = K) : (K = gu(
                A.type,
                A.key,
                A.props,
                null,
                N.mode,
                K
              ), En(K, A), K.return = N, N = K);
            }
            return c(N);
          case X:
            e: {
              for (oe = A.key; g !== null; ) {
                if (g.key === oe)
                  if (g.tag === 4 && g.stateNode.containerInfo === A.containerInfo && g.stateNode.implementation === A.implementation) {
                    l(
                      N,
                      g.sibling
                    ), K = u(g, A.children || []), K.return = N, N = K;
                    break e;
                  } else {
                    l(N, g);
                    break;
                  }
                else t(N, g);
                g = g.sibling;
              }
              K = es(A, N.mode, K), K.return = N, N = K;
            }
            return c(N);
          case F:
            return A = sa(A), Ge(
              N,
              g,
              A,
              K
            );
        }
        if (L(A))
          return se(
            N,
            g,
            A,
            K
          );
        if (h(A)) {
          if (oe = h(A), typeof oe != "function") throw Error(f(150));
          return A = oe.call(A), he(
            N,
            g,
            A,
            K
          );
        }
        if (typeof A.then == "function")
          return Ge(
            N,
            g,
            zu(A),
            K
          );
        if (A.$$typeof === V)
          return Ge(
            N,
            g,
            Su(N, A),
            K
          );
        Tu(N, A);
      }
      return typeof A == "string" && A !== "" || typeof A == "number" || typeof A == "bigint" ? (A = "" + A, g !== null && g.tag === 6 ? (l(N, g.sibling), K = u(g, A), K.return = N, N = K) : (l(N, g), K = Pi(A, N.mode, K), K.return = N, N = K), c(N)) : l(N, g);
    }
    return function(N, g, A, K) {
      try {
        Nn = 0;
        var oe = Ge(
          N,
          g,
          A,
          K
        );
        return Ba = null, oe;
      } catch (re) {
        if (re === qa || re === Eu) throw re;
        var Ae = Mt(29, re, null, N.mode);
        return Ae.lanes = K, Ae.return = N, Ae;
      } finally {
      }
    };
  }
  var ra = af(!0), nf = af(!1), Ml = !1;
  function ds(e) {
    e.updateQueue = {
      baseState: e.memoizedState,
      firstBaseUpdate: null,
      lastBaseUpdate: null,
      shared: { pending: null, lanes: 0, hiddenCallbacks: null },
      callbacks: null
    };
  }
  function ms(e, t) {
    e = e.updateQueue, t.updateQueue === e && (t.updateQueue = {
      baseState: e.baseState,
      firstBaseUpdate: e.firstBaseUpdate,
      lastBaseUpdate: e.lastBaseUpdate,
      shared: e.shared,
      callbacks: null
    });
  }
  function zl(e) {
    return { lane: e, tag: 0, payload: null, callback: null, next: null };
  }
  function Tl(e, t, l) {
    var a = e.updateQueue;
    if (a === null) return null;
    if (a = a.shared, (De & 2) !== 0) {
      var u = a.pending;
      return u === null ? t.next = t : (t.next = u.next, u.next = t), a.pending = t, t = xu(e), Lr(e, null, l), t;
    }
    return yu(e, a, t, l), xu(e);
  }
  function Mn(e, t, l) {
    if (t = t.updateQueue, t !== null && (t = t.shared, (l & 4194048) !== 0)) {
      var a = t.lanes;
      a &= e.pendingLanes, l |= a, t.lanes = l, $c(e, l);
    }
  }
  function hs(e, t) {
    var l = e.updateQueue, a = e.alternate;
    if (a !== null && (a = a.updateQueue, l === a)) {
      var u = null, i = null;
      if (l = l.firstBaseUpdate, l !== null) {
        do {
          var c = {
            lane: l.lane,
            tag: l.tag,
            payload: l.payload,
            callback: null,
            next: null
          };
          i === null ? u = i = c : i = i.next = c, l = l.next;
        } while (l !== null);
        i === null ? u = i = t : i = i.next = t;
      } else u = i = t;
      l = {
        baseState: a.baseState,
        firstBaseUpdate: u,
        lastBaseUpdate: i,
        shared: a.shared,
        callbacks: a.callbacks
      }, e.updateQueue = l;
      return;
    }
    e = l.lastBaseUpdate, e === null ? l.firstBaseUpdate = t : e.next = t, l.lastBaseUpdate = t;
  }
  var vs = !1;
  function zn() {
    if (vs) {
      var e = Ha;
      if (e !== null) throw e;
    }
  }
  function Tn(e, t, l, a) {
    vs = !1;
    var u = e.updateQueue;
    Ml = !1;
    var i = u.firstBaseUpdate, c = u.lastBaseUpdate, r = u.shared.pending;
    if (r !== null) {
      u.shared.pending = null;
      var v = r, C = v.next;
      v.next = null, c === null ? i = C : c.next = C, c = v;
      var Q = e.alternate;
      Q !== null && (Q = Q.updateQueue, r = Q.lastBaseUpdate, r !== c && (r === null ? Q.firstBaseUpdate = C : r.next = C, Q.lastBaseUpdate = v));
    }
    if (i !== null) {
      var $ = u.baseState;
      c = 0, Q = C = v = null, r = i;
      do {
        var R = r.lane & -536870913, B = R !== r.lane;
        if (B ? (Me & R) === R : (a & R) === R) {
          R !== 0 && R === Ua && (vs = !0), Q !== null && (Q = Q.next = {
            lane: 0,
            tag: r.tag,
            payload: r.payload,
            callback: null,
            next: null
          });
          e: {
            var se = e, he = r;
            R = t;
            var Ge = l;
            switch (he.tag) {
              case 1:
                if (se = he.payload, typeof se == "function") {
                  $ = se.call(Ge, $, R);
                  break e;
                }
                $ = se;
                break e;
              case 3:
                se.flags = se.flags & -65537 | 128;
              case 0:
                if (se = he.payload, R = typeof se == "function" ? se.call(Ge, $, R) : se, R == null) break e;
                $ = y({}, $, R);
                break e;
              case 2:
                Ml = !0;
            }
          }
          R = r.callback, R !== null && (e.flags |= 64, B && (e.flags |= 8192), B = u.callbacks, B === null ? u.callbacks = [R] : B.push(R));
        } else
          B = {
            lane: R,
            tag: r.tag,
            payload: r.payload,
            callback: r.callback,
            next: null
          }, Q === null ? (C = Q = B, v = $) : Q = Q.next = B, c |= R;
        if (r = r.next, r === null) {
          if (r = u.shared.pending, r === null)
            break;
          B = r, r = B.next, B.next = null, u.lastBaseUpdate = B, u.shared.pending = null;
        }
      } while (!0);
      Q === null && (v = $), u.baseState = v, u.firstBaseUpdate = C, u.lastBaseUpdate = Q, i === null && (u.shared.lanes = 0), Rl |= c, e.lanes = c, e.memoizedState = $;
    }
  }
  function uf(e, t) {
    if (typeof e != "function")
      throw Error(f(191, e));
    e.call(t);
  }
  function sf(e, t) {
    var l = e.callbacks;
    if (l !== null)
      for (e.callbacks = null, e = 0; e < l.length; e++)
        uf(l[e], t);
  }
  var Ya = m(null), Au = m(0);
  function cf(e, t) {
    e = vl, D(Au, e), D(Ya, t), vl = e | t.baseLanes;
  }
  function ps() {
    D(Au, vl), D(Ya, Ya.current);
  }
  function bs() {
    vl = Au.current, o(Ya), o(Au);
  }
  var zt = m(null), Xt = null;
  function Al(e) {
    var t = e.alternate;
    D(ke, ke.current & 1), D(zt, e), Xt === null && (t === null || Ya.current !== null || t.memoizedState !== null) && (Xt = e);
  }
  function ys(e) {
    D(ke, ke.current), D(zt, e), Xt === null && (Xt = e);
  }
  function rf(e) {
    e.tag === 22 ? (D(ke, ke.current), D(zt, e), Xt === null && (Xt = e)) : Cl();
  }
  function Cl() {
    D(ke, ke.current), D(zt, zt.current);
  }
  function Tt(e) {
    o(zt), Xt === e && (Xt = null), o(ke);
  }
  var ke = m(0);
  function Cu(e) {
    for (var t = e; t !== null; ) {
      if (t.tag === 13) {
        var l = t.memoizedState;
        if (l !== null && (l = l.dehydrated, l === null || Nc(l) || Ec(l)))
          return t;
      } else if (t.tag === 19 && (t.memoizedProps.revealOrder === "forwards" || t.memoizedProps.revealOrder === "backwards" || t.memoizedProps.revealOrder === "unstable_legacy-backwards" || t.memoizedProps.revealOrder === "together")) {
        if ((t.flags & 128) !== 0) return t;
      } else if (t.child !== null) {
        t.child.return = t, t = t.child;
        continue;
      }
      if (t === e) break;
      for (; t.sibling === null; ) {
        if (t.return === null || t.return === e) return null;
        t = t.return;
      }
      t.sibling.return = t.return, t = t.sibling;
    }
    return null;
  }
  var sl = 0, xe = null, Be = null, We = null, Ou = !1, Ga = !1, fa = !1, Du = 0, An = 0, Xa = null, xh = 0;
  function Ke() {
    throw Error(f(321));
  }
  function xs(e, t) {
    if (t === null) return !1;
    for (var l = 0; l < t.length && l < e.length; l++)
      if (!Et(e[l], t[l])) return !1;
    return !0;
  }
  function gs(e, t, l, a, u, i) {
    return sl = i, xe = t, t.memoizedState = null, t.updateQueue = null, t.lanes = 0, S.H = e === null || e.memoizedState === null ? Kf : Hs, fa = !1, i = l(a, u), fa = !1, Ga && (i = of(
      t,
      l,
      a,
      u
    )), ff(e), i;
  }
  function ff(e) {
    S.H = Dn;
    var t = Be !== null && Be.next !== null;
    if (sl = 0, We = Be = xe = null, Ou = !1, An = 0, Xa = null, t) throw Error(f(300));
    e === null || Ie || (e = e.dependencies, e !== null && _u(e) && (Ie = !0));
  }
  function of(e, t, l, a) {
    xe = e;
    var u = 0;
    do {
      if (Ga && (Xa = null), An = 0, Ga = !1, 25 <= u) throw Error(f(301));
      if (u += 1, We = Be = null, e.updateQueue != null) {
        var i = e.updateQueue;
        i.lastEffect = null, i.events = null, i.stores = null, i.memoCache != null && (i.memoCache.index = 0);
      }
      S.H = Jf, i = t(l, a);
    } while (Ga);
    return i;
  }
  function gh() {
    var e = S.H, t = e.useState()[0];
    return t = typeof t.then == "function" ? Cn(t) : t, e = e.useState()[0], (Be !== null ? Be.memoizedState : null) !== e && (xe.flags |= 1024), t;
  }
  function js() {
    var e = Du !== 0;
    return Du = 0, e;
  }
  function _s(e, t, l) {
    t.updateQueue = e.updateQueue, t.flags &= -2053, e.lanes &= ~l;
  }
  function Ss(e) {
    if (Ou) {
      for (e = e.memoizedState; e !== null; ) {
        var t = e.queue;
        t !== null && (t.pending = null), e = e.next;
      }
      Ou = !1;
    }
    sl = 0, We = Be = xe = null, Ga = !1, An = Du = 0, Xa = null;
  }
  function ht() {
    var e = {
      memoizedState: null,
      baseState: null,
      baseQueue: null,
      queue: null,
      next: null
    };
    return We === null ? xe.memoizedState = We = e : We = We.next = e, We;
  }
  function $e() {
    if (Be === null) {
      var e = xe.alternate;
      e = e !== null ? e.memoizedState : null;
    } else e = Be.next;
    var t = We === null ? xe.memoizedState : We.next;
    if (t !== null)
      We = t, Be = e;
    else {
      if (e === null)
        throw xe.alternate === null ? Error(f(467)) : Error(f(310));
      Be = e, e = {
        memoizedState: Be.memoizedState,
        baseState: Be.baseState,
        baseQueue: Be.baseQueue,
        queue: Be.queue,
        next: null
      }, We === null ? xe.memoizedState = We = e : We = We.next = e;
    }
    return We;
  }
  function Ru() {
    return { lastEffect: null, events: null, stores: null, memoCache: null };
  }
  function Cn(e) {
    var t = An;
    return An += 1, Xa === null && (Xa = []), e = ef(Xa, e, t), t = xe, (We === null ? t.memoizedState : We.next) === null && (t = t.alternate, S.H = t === null || t.memoizedState === null ? Kf : Hs), e;
  }
  function Uu(e) {
    if (e !== null && typeof e == "object") {
      if (typeof e.then == "function") return Cn(e);
      if (e.$$typeof === V) return st(e);
    }
    throw Error(f(438, String(e)));
  }
  function Ns(e) {
    var t = null, l = xe.updateQueue;
    if (l !== null && (t = l.memoCache), t == null) {
      var a = xe.alternate;
      a !== null && (a = a.updateQueue, a !== null && (a = a.memoCache, a != null && (t = {
        data: a.data.map(function(u) {
          return u.slice();
        }),
        index: 0
      })));
    }
    if (t == null && (t = { data: [], index: 0 }), l === null && (l = Ru(), xe.updateQueue = l), l.memoCache = t, l = t.data[t.index], l === void 0)
      for (l = t.data[t.index] = Array(e), a = 0; a < e; a++)
        l[a] = fe;
    return t.index++, l;
  }
  function cl(e, t) {
    return typeof t == "function" ? t(e) : t;
  }
  function Hu(e) {
    var t = $e();
    return Es(t, Be, e);
  }
  function Es(e, t, l) {
    var a = e.queue;
    if (a === null) throw Error(f(311));
    a.lastRenderedReducer = l;
    var u = e.baseQueue, i = a.pending;
    if (i !== null) {
      if (u !== null) {
        var c = u.next;
        u.next = i.next, i.next = c;
      }
      t.baseQueue = u = i, a.pending = null;
    }
    if (i = e.baseState, u === null) e.memoizedState = i;
    else {
      t = u.next;
      var r = c = null, v = null, C = t, Q = !1;
      do {
        var $ = C.lane & -536870913;
        if ($ !== C.lane ? (Me & $) === $ : (sl & $) === $) {
          var R = C.revertLane;
          if (R === 0)
            v !== null && (v = v.next = {
              lane: 0,
              revertLane: 0,
              gesture: null,
              action: C.action,
              hasEagerState: C.hasEagerState,
              eagerState: C.eagerState,
              next: null
            }), $ === Ua && (Q = !0);
          else if ((sl & R) === R) {
            C = C.next, R === Ua && (Q = !0);
            continue;
          } else
            $ = {
              lane: 0,
              revertLane: C.revertLane,
              gesture: null,
              action: C.action,
              hasEagerState: C.hasEagerState,
              eagerState: C.eagerState,
              next: null
            }, v === null ? (r = v = $, c = i) : v = v.next = $, xe.lanes |= R, Rl |= R;
          $ = C.action, fa && l(i, $), i = C.hasEagerState ? C.eagerState : l(i, $);
        } else
          R = {
            lane: $,
            revertLane: C.revertLane,
            gesture: C.gesture,
            action: C.action,
            hasEagerState: C.hasEagerState,
            eagerState: C.eagerState,
            next: null
          }, v === null ? (r = v = R, c = i) : v = v.next = R, xe.lanes |= $, Rl |= $;
        C = C.next;
      } while (C !== null && C !== t);
      if (v === null ? c = i : v.next = r, !Et(i, e.memoizedState) && (Ie = !0, Q && (l = Ha, l !== null)))
        throw l;
      e.memoizedState = i, e.baseState = c, e.baseQueue = v, a.lastRenderedState = i;
    }
    return u === null && (a.lanes = 0), [e.memoizedState, a.dispatch];
  }
  function Ms(e) {
    var t = $e(), l = t.queue;
    if (l === null) throw Error(f(311));
    l.lastRenderedReducer = e;
    var a = l.dispatch, u = l.pending, i = t.memoizedState;
    if (u !== null) {
      l.pending = null;
      var c = u = u.next;
      do
        i = e(i, c.action), c = c.next;
      while (c !== u);
      Et(i, t.memoizedState) || (Ie = !0), t.memoizedState = i, t.baseQueue === null && (t.baseState = i), l.lastRenderedState = i;
    }
    return [i, a];
  }
  function df(e, t, l) {
    var a = xe, u = $e(), i = Te;
    if (i) {
      if (l === void 0) throw Error(f(407));
      l = l();
    } else l = t();
    var c = !Et(
      (Be || u).memoizedState,
      l
    );
    if (c && (u.memoizedState = l, Ie = !0), u = u.queue, As(vf.bind(null, a, u, e), [
      e
    ]), u.getSnapshot !== t || c || We !== null && We.memoizedState.tag & 1) {
      if (a.flags |= 2048, La(
        9,
        { destroy: void 0 },
        hf.bind(
          null,
          a,
          u,
          l,
          t
        ),
        null
      ), Xe === null) throw Error(f(349));
      i || (sl & 127) !== 0 || mf(a, t, l);
    }
    return l;
  }
  function mf(e, t, l) {
    e.flags |= 16384, e = { getSnapshot: t, value: l }, t = xe.updateQueue, t === null ? (t = Ru(), xe.updateQueue = t, t.stores = [e]) : (l = t.stores, l === null ? t.stores = [e] : l.push(e));
  }
  function hf(e, t, l, a) {
    t.value = l, t.getSnapshot = a, pf(t) && bf(e);
  }
  function vf(e, t, l) {
    return l(function() {
      pf(t) && bf(e);
    });
  }
  function pf(e) {
    var t = e.getSnapshot;
    e = e.value;
    try {
      var l = t();
      return !Et(e, l);
    } catch {
      return !0;
    }
  }
  function bf(e) {
    var t = ta(e, 2);
    t !== null && _t(t, e, 2);
  }
  function zs(e) {
    var t = ht();
    if (typeof e == "function") {
      var l = e;
      if (e = l(), fa) {
        xl(!0);
        try {
          l();
        } finally {
          xl(!1);
        }
      }
    }
    return t.memoizedState = t.baseState = e, t.queue = {
      pending: null,
      lanes: 0,
      dispatch: null,
      lastRenderedReducer: cl,
      lastRenderedState: e
    }, t;
  }
  function yf(e, t, l, a) {
    return e.baseState = l, Es(
      e,
      Be,
      typeof a == "function" ? a : cl
    );
  }
  function jh(e, t, l, a, u) {
    if (Yu(e)) throw Error(f(485));
    if (e = t.action, e !== null) {
      var i = {
        payload: u,
        action: e,
        next: null,
        isTransition: !0,
        status: "pending",
        value: null,
        reason: null,
        listeners: [],
        then: function(c) {
          i.listeners.push(c);
        }
      };
      S.T !== null ? l(!0) : i.isTransition = !1, a(i), l = t.pending, l === null ? (i.next = t.pending = i, xf(t, i)) : (i.next = l.next, t.pending = l.next = i);
    }
  }
  function xf(e, t) {
    var l = t.action, a = t.payload, u = e.state;
    if (t.isTransition) {
      var i = S.T, c = {};
      S.T = c;
      try {
        var r = l(u, a), v = S.S;
        v !== null && v(c, r), gf(e, t, r);
      } catch (C) {
        Ts(e, t, C);
      } finally {
        i !== null && c.types !== null && (i.types = c.types), S.T = i;
      }
    } else
      try {
        i = l(u, a), gf(e, t, i);
      } catch (C) {
        Ts(e, t, C);
      }
  }
  function gf(e, t, l) {
    l !== null && typeof l == "object" && typeof l.then == "function" ? l.then(
      function(a) {
        jf(e, t, a);
      },
      function(a) {
        return Ts(e, t, a);
      }
    ) : jf(e, t, l);
  }
  function jf(e, t, l) {
    t.status = "fulfilled", t.value = l, _f(t), e.state = l, t = e.pending, t !== null && (l = t.next, l === t ? e.pending = null : (l = l.next, t.next = l, xf(e, l)));
  }
  function Ts(e, t, l) {
    var a = e.pending;
    if (e.pending = null, a !== null) {
      a = a.next;
      do
        t.status = "rejected", t.reason = l, _f(t), t = t.next;
      while (t !== a);
    }
    e.action = null;
  }
  function _f(e) {
    e = e.listeners;
    for (var t = 0; t < e.length; t++) (0, e[t])();
  }
  function Sf(e, t) {
    return t;
  }
  function Nf(e, t) {
    if (Te) {
      var l = Xe.formState;
      if (l !== null) {
        e: {
          var a = xe;
          if (Te) {
            if (Le) {
              t: {
                for (var u = Le, i = Gt; u.nodeType !== 8; ) {
                  if (!i) {
                    u = null;
                    break t;
                  }
                  if (u = Lt(
                    u.nextSibling
                  ), u === null) {
                    u = null;
                    break t;
                  }
                }
                i = u.data, u = i === "F!" || i === "F" ? u : null;
              }
              if (u) {
                Le = Lt(
                  u.nextSibling
                ), a = u.data === "F!";
                break e;
              }
            }
            Nl(a);
          }
          a = !1;
        }
        a && (t = l[0]);
      }
    }
    return l = ht(), l.memoizedState = l.baseState = t, a = {
      pending: null,
      lanes: 0,
      dispatch: null,
      lastRenderedReducer: Sf,
      lastRenderedState: t
    }, l.queue = a, l = Zf.bind(
      null,
      xe,
      a
    ), a.dispatch = l, a = zs(!1), i = Us.bind(
      null,
      xe,
      !1,
      a.queue
    ), a = ht(), u = {
      state: t,
      dispatch: null,
      action: e,
      pending: null
    }, a.queue = u, l = jh.bind(
      null,
      xe,
      u,
      i,
      l
    ), u.dispatch = l, a.memoizedState = e, [t, l, !1];
  }
  function Ef(e) {
    var t = $e();
    return Mf(t, Be, e);
  }
  function Mf(e, t, l) {
    if (t = Es(
      e,
      t,
      Sf
    )[0], e = Hu(cl)[0], typeof t == "object" && t !== null && typeof t.then == "function")
      try {
        var a = Cn(t);
      } catch (c) {
        throw c === qa ? Eu : c;
      }
    else a = t;
    t = $e();
    var u = t.queue, i = u.dispatch;
    return l !== t.memoizedState && (xe.flags |= 2048, La(
      9,
      { destroy: void 0 },
      _h.bind(null, u, l),
      null
    )), [a, i, e];
  }
  function _h(e, t) {
    e.action = t;
  }
  function zf(e) {
    var t = $e(), l = Be;
    if (l !== null)
      return Mf(t, l, e);
    $e(), t = t.memoizedState, l = $e();
    var a = l.queue.dispatch;
    return l.memoizedState = e, [t, a, !1];
  }
  function La(e, t, l, a) {
    return e = { tag: e, create: l, deps: a, inst: t, next: null }, t = xe.updateQueue, t === null && (t = Ru(), xe.updateQueue = t), l = t.lastEffect, l === null ? t.lastEffect = e.next = e : (a = l.next, l.next = e, e.next = a, t.lastEffect = e), e;
  }
  function Tf() {
    return $e().memoizedState;
  }
  function qu(e, t, l, a) {
    var u = ht();
    xe.flags |= e, u.memoizedState = La(
      1 | t,
      { destroy: void 0 },
      l,
      a === void 0 ? null : a
    );
  }
  function Bu(e, t, l, a) {
    var u = $e();
    a = a === void 0 ? null : a;
    var i = u.memoizedState.inst;
    Be !== null && a !== null && xs(a, Be.memoizedState.deps) ? u.memoizedState = La(t, i, l, a) : (xe.flags |= e, u.memoizedState = La(
      1 | t,
      i,
      l,
      a
    ));
  }
  function Af(e, t) {
    qu(8390656, 8, e, t);
  }
  function As(e, t) {
    Bu(2048, 8, e, t);
  }
  function Sh(e) {
    xe.flags |= 4;
    var t = xe.updateQueue;
    if (t === null)
      t = Ru(), xe.updateQueue = t, t.events = [e];
    else {
      var l = t.events;
      l === null ? t.events = [e] : l.push(e);
    }
  }
  function Cf(e) {
    var t = $e().memoizedState;
    return Sh({ ref: t, nextImpl: e }), function() {
      if ((De & 2) !== 0) throw Error(f(440));
      return t.impl.apply(void 0, arguments);
    };
  }
  function Of(e, t) {
    return Bu(4, 2, e, t);
  }
  function Df(e, t) {
    return Bu(4, 4, e, t);
  }
  function Rf(e, t) {
    if (typeof t == "function") {
      e = e();
      var l = t(e);
      return function() {
        typeof l == "function" ? l() : t(null);
      };
    }
    if (t != null)
      return e = e(), t.current = e, function() {
        t.current = null;
      };
  }
  function Uf(e, t, l) {
    l = l != null ? l.concat([e]) : null, Bu(4, 4, Rf.bind(null, t, e), l);
  }
  function Cs() {
  }
  function Hf(e, t) {
    var l = $e();
    t = t === void 0 ? null : t;
    var a = l.memoizedState;
    return t !== null && xs(t, a[1]) ? a[0] : (l.memoizedState = [e, t], e);
  }
  function qf(e, t) {
    var l = $e();
    t = t === void 0 ? null : t;
    var a = l.memoizedState;
    if (t !== null && xs(t, a[1]))
      return a[0];
    if (a = e(), fa) {
      xl(!0);
      try {
        e();
      } finally {
        xl(!1);
      }
    }
    return l.memoizedState = [a, t], a;
  }
  function Os(e, t, l) {
    return l === void 0 || (sl & 1073741824) !== 0 && (Me & 261930) === 0 ? e.memoizedState = t : (e.memoizedState = l, e = Yo(), xe.lanes |= e, Rl |= e, l);
  }
  function Bf(e, t, l, a) {
    return Et(l, t) ? l : Ya.current !== null ? (e = Os(e, l, a), Et(e, t) || (Ie = !0), e) : (sl & 42) === 0 || (sl & 1073741824) !== 0 && (Me & 261930) === 0 ? (Ie = !0, e.memoizedState = l) : (e = Yo(), xe.lanes |= e, Rl |= e, t);
  }
  function Yf(e, t, l, a, u) {
    var i = Z.p;
    Z.p = i !== 0 && 8 > i ? i : 8;
    var c = S.T, r = {};
    S.T = r, Us(e, !1, t, l);
    try {
      var v = u(), C = S.S;
      if (C !== null && C(r, v), v !== null && typeof v == "object" && typeof v.then == "function") {
        var Q = yh(
          v,
          a
        );
        On(
          e,
          t,
          Q,
          Ot(e)
        );
      } else
        On(
          e,
          t,
          a,
          Ot(e)
        );
    } catch ($) {
      On(
        e,
        t,
        { then: function() {
        }, status: "rejected", reason: $ },
        Ot()
      );
    } finally {
      Z.p = i, c !== null && r.types !== null && (c.types = r.types), S.T = c;
    }
  }
  function Nh() {
  }
  function Ds(e, t, l, a) {
    if (e.tag !== 5) throw Error(f(476));
    var u = Gf(e).queue;
    Yf(
      e,
      u,
      t,
      ne,
      l === null ? Nh : function() {
        return Xf(e), l(a);
      }
    );
  }
  function Gf(e) {
    var t = e.memoizedState;
    if (t !== null) return t;
    t = {
      memoizedState: ne,
      baseState: ne,
      baseQueue: null,
      queue: {
        pending: null,
        lanes: 0,
        dispatch: null,
        lastRenderedReducer: cl,
        lastRenderedState: ne
      },
      next: null
    };
    var l = {};
    return t.next = {
      memoizedState: l,
      baseState: l,
      baseQueue: null,
      queue: {
        pending: null,
        lanes: 0,
        dispatch: null,
        lastRenderedReducer: cl,
        lastRenderedState: l
      },
      next: null
    }, e.memoizedState = t, e = e.alternate, e !== null && (e.memoizedState = t), t;
  }
  function Xf(e) {
    var t = Gf(e);
    t.next === null && (t = e.alternate.memoizedState), On(
      e,
      t.next.queue,
      {},
      Ot()
    );
  }
  function Rs() {
    return st(kn);
  }
  function Lf() {
    return $e().memoizedState;
  }
  function Qf() {
    return $e().memoizedState;
  }
  function Eh(e) {
    for (var t = e.return; t !== null; ) {
      switch (t.tag) {
        case 24:
        case 3:
          var l = Ot();
          e = zl(l);
          var a = Tl(t, e, l);
          a !== null && (_t(a, t, l), Mn(a, t, l)), t = { cache: cs() }, e.payload = t;
          return;
      }
      t = t.return;
    }
  }
  function Mh(e, t, l) {
    var a = Ot();
    l = {
      lane: a,
      revertLane: 0,
      gesture: null,
      action: l,
      hasEagerState: !1,
      eagerState: null,
      next: null
    }, Yu(e) ? wf(t, l) : (l = Wi(e, t, l, a), l !== null && (_t(l, e, a), Vf(l, t, a)));
  }
  function Zf(e, t, l) {
    var a = Ot();
    On(e, t, l, a);
  }
  function On(e, t, l, a) {
    var u = {
      lane: a,
      revertLane: 0,
      gesture: null,
      action: l,
      hasEagerState: !1,
      eagerState: null,
      next: null
    };
    if (Yu(e)) wf(t, u);
    else {
      var i = e.alternate;
      if (e.lanes === 0 && (i === null || i.lanes === 0) && (i = t.lastRenderedReducer, i !== null))
        try {
          var c = t.lastRenderedState, r = i(c, l);
          if (u.hasEagerState = !0, u.eagerState = r, Et(r, c))
            return yu(e, t, u, 0), Xe === null && bu(), !1;
        } catch {
        } finally {
        }
      if (l = Wi(e, t, u, a), l !== null)
        return _t(l, e, a), Vf(l, t, a), !0;
    }
    return !1;
  }
  function Us(e, t, l, a) {
    if (a = {
      lane: 2,
      revertLane: dc(),
      gesture: null,
      action: a,
      hasEagerState: !1,
      eagerState: null,
      next: null
    }, Yu(e)) {
      if (t) throw Error(f(479));
    } else
      t = Wi(
        e,
        l,
        a,
        2
      ), t !== null && _t(t, e, 2);
  }
  function Yu(e) {
    var t = e.alternate;
    return e === xe || t !== null && t === xe;
  }
  function wf(e, t) {
    Ga = Ou = !0;
    var l = e.pending;
    l === null ? t.next = t : (t.next = l.next, l.next = t), e.pending = t;
  }
  function Vf(e, t, l) {
    if ((l & 4194048) !== 0) {
      var a = t.lanes;
      a &= e.pendingLanes, l |= a, t.lanes = l, $c(e, l);
    }
  }
  var Dn = {
    readContext: st,
    use: Uu,
    useCallback: Ke,
    useContext: Ke,
    useEffect: Ke,
    useImperativeHandle: Ke,
    useLayoutEffect: Ke,
    useInsertionEffect: Ke,
    useMemo: Ke,
    useReducer: Ke,
    useRef: Ke,
    useState: Ke,
    useDebugValue: Ke,
    useDeferredValue: Ke,
    useTransition: Ke,
    useSyncExternalStore: Ke,
    useId: Ke,
    useHostTransitionStatus: Ke,
    useFormState: Ke,
    useActionState: Ke,
    useOptimistic: Ke,
    useMemoCache: Ke,
    useCacheRefresh: Ke
  };
  Dn.useEffectEvent = Ke;
  var Kf = {
    readContext: st,
    use: Uu,
    useCallback: function(e, t) {
      return ht().memoizedState = [
        e,
        t === void 0 ? null : t
      ], e;
    },
    useContext: st,
    useEffect: Af,
    useImperativeHandle: function(e, t, l) {
      l = l != null ? l.concat([e]) : null, qu(
        4194308,
        4,
        Rf.bind(null, t, e),
        l
      );
    },
    useLayoutEffect: function(e, t) {
      return qu(4194308, 4, e, t);
    },
    useInsertionEffect: function(e, t) {
      qu(4, 2, e, t);
    },
    useMemo: function(e, t) {
      var l = ht();
      t = t === void 0 ? null : t;
      var a = e();
      if (fa) {
        xl(!0);
        try {
          e();
        } finally {
          xl(!1);
        }
      }
      return l.memoizedState = [a, t], a;
    },
    useReducer: function(e, t, l) {
      var a = ht();
      if (l !== void 0) {
        var u = l(t);
        if (fa) {
          xl(!0);
          try {
            l(t);
          } finally {
            xl(!1);
          }
        }
      } else u = t;
      return a.memoizedState = a.baseState = u, e = {
        pending: null,
        lanes: 0,
        dispatch: null,
        lastRenderedReducer: e,
        lastRenderedState: u
      }, a.queue = e, e = e.dispatch = Mh.bind(
        null,
        xe,
        e
      ), [a.memoizedState, e];
    },
    useRef: function(e) {
      var t = ht();
      return e = { current: e }, t.memoizedState = e;
    },
    useState: function(e) {
      e = zs(e);
      var t = e.queue, l = Zf.bind(null, xe, t);
      return t.dispatch = l, [e.memoizedState, l];
    },
    useDebugValue: Cs,
    useDeferredValue: function(e, t) {
      var l = ht();
      return Os(l, e, t);
    },
    useTransition: function() {
      var e = zs(!1);
      return e = Yf.bind(
        null,
        xe,
        e.queue,
        !0,
        !1
      ), ht().memoizedState = e, [!1, e];
    },
    useSyncExternalStore: function(e, t, l) {
      var a = xe, u = ht();
      if (Te) {
        if (l === void 0)
          throw Error(f(407));
        l = l();
      } else {
        if (l = t(), Xe === null)
          throw Error(f(349));
        (Me & 127) !== 0 || mf(a, t, l);
      }
      u.memoizedState = l;
      var i = { value: l, getSnapshot: t };
      return u.queue = i, Af(vf.bind(null, a, i, e), [
        e
      ]), a.flags |= 2048, La(
        9,
        { destroy: void 0 },
        hf.bind(
          null,
          a,
          i,
          l,
          t
        ),
        null
      ), l;
    },
    useId: function() {
      var e = ht(), t = Xe.identifierPrefix;
      if (Te) {
        var l = $t, a = kt;
        l = (a & ~(1 << 32 - Nt(a) - 1)).toString(32) + l, t = "_" + t + "R_" + l, l = Du++, 0 < l && (t += "H" + l.toString(32)), t += "_";
      } else
        l = xh++, t = "_" + t + "r_" + l.toString(32) + "_";
      return e.memoizedState = t;
    },
    useHostTransitionStatus: Rs,
    useFormState: Nf,
    useActionState: Nf,
    useOptimistic: function(e) {
      var t = ht();
      t.memoizedState = t.baseState = e;
      var l = {
        pending: null,
        lanes: 0,
        dispatch: null,
        lastRenderedReducer: null,
        lastRenderedState: null
      };
      return t.queue = l, t = Us.bind(
        null,
        xe,
        !0,
        l
      ), l.dispatch = t, [e, t];
    },
    useMemoCache: Ns,
    useCacheRefresh: function() {
      return ht().memoizedState = Eh.bind(
        null,
        xe
      );
    },
    useEffectEvent: function(e) {
      var t = ht(), l = { impl: e };
      return t.memoizedState = l, function() {
        if ((De & 2) !== 0)
          throw Error(f(440));
        return l.impl.apply(void 0, arguments);
      };
    }
  }, Hs = {
    readContext: st,
    use: Uu,
    useCallback: Hf,
    useContext: st,
    useEffect: As,
    useImperativeHandle: Uf,
    useInsertionEffect: Of,
    useLayoutEffect: Df,
    useMemo: qf,
    useReducer: Hu,
    useRef: Tf,
    useState: function() {
      return Hu(cl);
    },
    useDebugValue: Cs,
    useDeferredValue: function(e, t) {
      var l = $e();
      return Bf(
        l,
        Be.memoizedState,
        e,
        t
      );
    },
    useTransition: function() {
      var e = Hu(cl)[0], t = $e().memoizedState;
      return [
        typeof e == "boolean" ? e : Cn(e),
        t
      ];
    },
    useSyncExternalStore: df,
    useId: Lf,
    useHostTransitionStatus: Rs,
    useFormState: Ef,
    useActionState: Ef,
    useOptimistic: function(e, t) {
      var l = $e();
      return yf(l, Be, e, t);
    },
    useMemoCache: Ns,
    useCacheRefresh: Qf
  };
  Hs.useEffectEvent = Cf;
  var Jf = {
    readContext: st,
    use: Uu,
    useCallback: Hf,
    useContext: st,
    useEffect: As,
    useImperativeHandle: Uf,
    useInsertionEffect: Of,
    useLayoutEffect: Df,
    useMemo: qf,
    useReducer: Ms,
    useRef: Tf,
    useState: function() {
      return Ms(cl);
    },
    useDebugValue: Cs,
    useDeferredValue: function(e, t) {
      var l = $e();
      return Be === null ? Os(l, e, t) : Bf(
        l,
        Be.memoizedState,
        e,
        t
      );
    },
    useTransition: function() {
      var e = Ms(cl)[0], t = $e().memoizedState;
      return [
        typeof e == "boolean" ? e : Cn(e),
        t
      ];
    },
    useSyncExternalStore: df,
    useId: Lf,
    useHostTransitionStatus: Rs,
    useFormState: zf,
    useActionState: zf,
    useOptimistic: function(e, t) {
      var l = $e();
      return Be !== null ? yf(l, Be, e, t) : (l.baseState = e, [e, l.queue.dispatch]);
    },
    useMemoCache: Ns,
    useCacheRefresh: Qf
  };
  Jf.useEffectEvent = Cf;
  function qs(e, t, l, a) {
    t = e.memoizedState, l = l(a, t), l = l == null ? t : y({}, t, l), e.memoizedState = l, e.lanes === 0 && (e.updateQueue.baseState = l);
  }
  var Bs = {
    enqueueSetState: function(e, t, l) {
      e = e._reactInternals;
      var a = Ot(), u = zl(a);
      u.payload = t, l != null && (u.callback = l), t = Tl(e, u, a), t !== null && (_t(t, e, a), Mn(t, e, a));
    },
    enqueueReplaceState: function(e, t, l) {
      e = e._reactInternals;
      var a = Ot(), u = zl(a);
      u.tag = 1, u.payload = t, l != null && (u.callback = l), t = Tl(e, u, a), t !== null && (_t(t, e, a), Mn(t, e, a));
    },
    enqueueForceUpdate: function(e, t) {
      e = e._reactInternals;
      var l = Ot(), a = zl(l);
      a.tag = 2, t != null && (a.callback = t), t = Tl(e, a, l), t !== null && (_t(t, e, l), Mn(t, e, l));
    }
  };
  function kf(e, t, l, a, u, i, c) {
    return e = e.stateNode, typeof e.shouldComponentUpdate == "function" ? e.shouldComponentUpdate(a, i, c) : t.prototype && t.prototype.isPureReactComponent ? !yn(l, a) || !yn(u, i) : !0;
  }
  function $f(e, t, l, a) {
    e = t.state, typeof t.componentWillReceiveProps == "function" && t.componentWillReceiveProps(l, a), typeof t.UNSAFE_componentWillReceiveProps == "function" && t.UNSAFE_componentWillReceiveProps(l, a), t.state !== e && Bs.enqueueReplaceState(t, t.state, null);
  }
  function oa(e, t) {
    var l = t;
    if ("ref" in t) {
      l = {};
      for (var a in t)
        a !== "ref" && (l[a] = t[a]);
    }
    if (e = e.defaultProps) {
      l === t && (l = y({}, l));
      for (var u in e)
        l[u] === void 0 && (l[u] = e[u]);
    }
    return l;
  }
  function Ff(e) {
    pu(e);
  }
  function Wf(e) {
    console.error(e);
  }
  function If(e) {
    pu(e);
  }
  function Gu(e, t) {
    try {
      var l = e.onUncaughtError;
      l(t.value, { componentStack: t.stack });
    } catch (a) {
      setTimeout(function() {
        throw a;
      });
    }
  }
  function Pf(e, t, l) {
    try {
      var a = e.onCaughtError;
      a(l.value, {
        componentStack: l.stack,
        errorBoundary: t.tag === 1 ? t.stateNode : null
      });
    } catch (u) {
      setTimeout(function() {
        throw u;
      });
    }
  }
  function Ys(e, t, l) {
    return l = zl(l), l.tag = 3, l.payload = { element: null }, l.callback = function() {
      Gu(e, t);
    }, l;
  }
  function eo(e) {
    return e = zl(e), e.tag = 3, e;
  }
  function to(e, t, l, a) {
    var u = l.type.getDerivedStateFromError;
    if (typeof u == "function") {
      var i = a.value;
      e.payload = function() {
        return u(i);
      }, e.callback = function() {
        Pf(t, l, a);
      };
    }
    var c = l.stateNode;
    c !== null && typeof c.componentDidCatch == "function" && (e.callback = function() {
      Pf(t, l, a), typeof u != "function" && (Ul === null ? Ul = /* @__PURE__ */ new Set([this]) : Ul.add(this));
      var r = a.stack;
      this.componentDidCatch(a.value, {
        componentStack: r !== null ? r : ""
      });
    });
  }
  function zh(e, t, l, a, u) {
    if (l.flags |= 32768, a !== null && typeof a == "object" && typeof a.then == "function") {
      if (t = l.alternate, t !== null && Ra(
        t,
        l,
        u,
        !0
      ), l = zt.current, l !== null) {
        switch (l.tag) {
          case 31:
          case 13:
            return Xt === null ? Wu() : l.alternate === null && Je === 0 && (Je = 3), l.flags &= -257, l.flags |= 65536, l.lanes = u, a === Mu ? l.flags |= 16384 : (t = l.updateQueue, t === null ? l.updateQueue = /* @__PURE__ */ new Set([a]) : t.add(a), rc(e, a, u)), !1;
          case 22:
            return l.flags |= 65536, a === Mu ? l.flags |= 16384 : (t = l.updateQueue, t === null ? (t = {
              transitions: null,
              markerInstances: null,
              retryQueue: /* @__PURE__ */ new Set([a])
            }, l.updateQueue = t) : (l = t.retryQueue, l === null ? t.retryQueue = /* @__PURE__ */ new Set([a]) : l.add(a)), rc(e, a, u)), !1;
        }
        throw Error(f(435, l.tag));
      }
      return rc(e, a, u), Wu(), !1;
    }
    if (Te)
      return t = zt.current, t !== null ? ((t.flags & 65536) === 0 && (t.flags |= 256), t.flags |= 65536, t.lanes = u, a !== as && (e = Error(f(422), { cause: a }), jn(qt(e, l)))) : (a !== as && (t = Error(f(423), {
        cause: a
      }), jn(
        qt(t, l)
      )), e = e.current.alternate, e.flags |= 65536, u &= -u, e.lanes |= u, a = qt(a, l), u = Ys(
        e.stateNode,
        a,
        u
      ), hs(e, u), Je !== 4 && (Je = 2)), !1;
    var i = Error(f(520), { cause: a });
    if (i = qt(i, l), Xn === null ? Xn = [i] : Xn.push(i), Je !== 4 && (Je = 2), t === null) return !0;
    a = qt(a, l), l = t;
    do {
      switch (l.tag) {
        case 3:
          return l.flags |= 65536, e = u & -u, l.lanes |= e, e = Ys(l.stateNode, a, e), hs(l, e), !1;
        case 1:
          if (t = l.type, i = l.stateNode, (l.flags & 128) === 0 && (typeof t.getDerivedStateFromError == "function" || i !== null && typeof i.componentDidCatch == "function" && (Ul === null || !Ul.has(i))))
            return l.flags |= 65536, u &= -u, l.lanes |= u, u = eo(u), to(
              u,
              e,
              l,
              a
            ), hs(l, u), !1;
      }
      l = l.return;
    } while (l !== null);
    return !1;
  }
  var Gs = Error(f(461)), Ie = !1;
  function ct(e, t, l, a) {
    t.child = e === null ? nf(t, null, l, a) : ra(
      t,
      e.child,
      l,
      a
    );
  }
  function lo(e, t, l, a, u) {
    l = l.render;
    var i = t.ref;
    if ("ref" in a) {
      var c = {};
      for (var r in a)
        r !== "ref" && (c[r] = a[r]);
    } else c = a;
    return ua(t), a = gs(
      e,
      t,
      l,
      c,
      i,
      u
    ), r = js(), e !== null && !Ie ? (_s(e, t, u), rl(e, t, u)) : (Te && r && ts(t), t.flags |= 1, ct(e, t, a, u), t.child);
  }
  function ao(e, t, l, a, u) {
    if (e === null) {
      var i = l.type;
      return typeof i == "function" && !Ii(i) && i.defaultProps === void 0 && l.compare === null ? (t.tag = 15, t.type = i, no(
        e,
        t,
        i,
        a,
        u
      )) : (e = gu(
        l.type,
        null,
        a,
        t,
        t.mode,
        u
      ), e.ref = t.ref, e.return = t, t.child = e);
    }
    if (i = e.child, !Js(e, u)) {
      var c = i.memoizedProps;
      if (l = l.compare, l = l !== null ? l : yn, l(c, a) && e.ref === t.ref)
        return rl(e, t, u);
    }
    return t.flags |= 1, e = al(i, a), e.ref = t.ref, e.return = t, t.child = e;
  }
  function no(e, t, l, a, u) {
    if (e !== null) {
      var i = e.memoizedProps;
      if (yn(i, a) && e.ref === t.ref)
        if (Ie = !1, t.pendingProps = a = i, Js(e, u))
          (e.flags & 131072) !== 0 && (Ie = !0);
        else
          return t.lanes = e.lanes, rl(e, t, u);
    }
    return Xs(
      e,
      t,
      l,
      a,
      u
    );
  }
  function uo(e, t, l, a) {
    var u = a.children, i = e !== null ? e.memoizedState : null;
    if (e === null && t.stateNode === null && (t.stateNode = {
      _visibility: 1,
      _pendingMarkers: null,
      _retryCache: null,
      _transitions: null
    }), a.mode === "hidden") {
      if ((t.flags & 128) !== 0) {
        if (i = i !== null ? i.baseLanes | l : l, e !== null) {
          for (a = t.child = e.child, u = 0; a !== null; )
            u = u | a.lanes | a.childLanes, a = a.sibling;
          a = u & ~i;
        } else a = 0, t.child = null;
        return io(
          e,
          t,
          i,
          l,
          a
        );
      }
      if ((l & 536870912) !== 0)
        t.memoizedState = { baseLanes: 0, cachePool: null }, e !== null && Nu(
          t,
          i !== null ? i.cachePool : null
        ), i !== null ? cf(t, i) : ps(), rf(t);
      else
        return a = t.lanes = 536870912, io(
          e,
          t,
          i !== null ? i.baseLanes | l : l,
          l,
          a
        );
    } else
      i !== null ? (Nu(t, i.cachePool), cf(t, i), Cl(), t.memoizedState = null) : (e !== null && Nu(t, null), ps(), Cl());
    return ct(e, t, u, l), t.child;
  }
  function Rn(e, t) {
    return e !== null && e.tag === 22 || t.stateNode !== null || (t.stateNode = {
      _visibility: 1,
      _pendingMarkers: null,
      _retryCache: null,
      _transitions: null
    }), t.sibling;
  }
  function io(e, t, l, a, u) {
    var i = fs();
    return i = i === null ? null : { parent: Fe._currentValue, pool: i }, t.memoizedState = {
      baseLanes: l,
      cachePool: i
    }, e !== null && Nu(t, null), ps(), rf(t), e !== null && Ra(e, t, a, !0), t.childLanes = u, null;
  }
  function Xu(e, t) {
    return t = Qu(
      { mode: t.mode, children: t.children },
      e.mode
    ), t.ref = e.ref, e.child = t, t.return = e, t;
  }
  function so(e, t, l) {
    return ra(t, e.child, null, l), e = Xu(t, t.pendingProps), e.flags |= 2, Tt(t), t.memoizedState = null, e;
  }
  function Th(e, t, l) {
    var a = t.pendingProps, u = (t.flags & 128) !== 0;
    if (t.flags &= -129, e === null) {
      if (Te) {
        if (a.mode === "hidden")
          return e = Xu(t, a), t.lanes = 536870912, Rn(null, e);
        if (ys(t), (e = Le) ? (e = gd(
          e,
          Gt
        ), e = e !== null && e.data === "&" ? e : null, e !== null && (t.memoizedState = {
          dehydrated: e,
          treeContext: _l !== null ? { id: kt, overflow: $t } : null,
          retryLane: 536870912,
          hydrationErrors: null
        }, l = Zr(e), l.return = t, t.child = l, it = t, Le = null)) : e = null, e === null) throw Nl(t);
        return t.lanes = 536870912, null;
      }
      return Xu(t, a);
    }
    var i = e.memoizedState;
    if (i !== null) {
      var c = i.dehydrated;
      if (ys(t), u)
        if (t.flags & 256)
          t.flags &= -257, t = so(
            e,
            t,
            l
          );
        else if (t.memoizedState !== null)
          t.child = e.child, t.flags |= 128, t = null;
        else throw Error(f(558));
      else if (Ie || Ra(e, t, l, !1), u = (l & e.childLanes) !== 0, Ie || u) {
        if (a = Xe, a !== null && (c = Fc(a, l), c !== 0 && c !== i.retryLane))
          throw i.retryLane = c, ta(e, c), _t(a, e, c), Gs;
        Wu(), t = so(
          e,
          t,
          l
        );
      } else
        e = i.treeContext, Le = Lt(c.nextSibling), it = t, Te = !0, Sl = null, Gt = !1, e !== null && Kr(t, e), t = Xu(t, a), t.flags |= 4096;
      return t;
    }
    return e = al(e.child, {
      mode: a.mode,
      children: a.children
    }), e.ref = t.ref, t.child = e, e.return = t, e;
  }
  function Lu(e, t) {
    var l = t.ref;
    if (l === null)
      e !== null && e.ref !== null && (t.flags |= 4194816);
    else {
      if (typeof l != "function" && typeof l != "object")
        throw Error(f(284));
      (e === null || e.ref !== l) && (t.flags |= 4194816);
    }
  }
  function Xs(e, t, l, a, u) {
    return ua(t), l = gs(
      e,
      t,
      l,
      a,
      void 0,
      u
    ), a = js(), e !== null && !Ie ? (_s(e, t, u), rl(e, t, u)) : (Te && a && ts(t), t.flags |= 1, ct(e, t, l, u), t.child);
  }
  function co(e, t, l, a, u, i) {
    return ua(t), t.updateQueue = null, l = of(
      t,
      a,
      l,
      u
    ), ff(e), a = js(), e !== null && !Ie ? (_s(e, t, i), rl(e, t, i)) : (Te && a && ts(t), t.flags |= 1, ct(e, t, l, i), t.child);
  }
  function ro(e, t, l, a, u) {
    if (ua(t), t.stateNode === null) {
      var i = Aa, c = l.contextType;
      typeof c == "object" && c !== null && (i = st(c)), i = new l(a, i), t.memoizedState = i.state !== null && i.state !== void 0 ? i.state : null, i.updater = Bs, t.stateNode = i, i._reactInternals = t, i = t.stateNode, i.props = a, i.state = t.memoizedState, i.refs = {}, ds(t), c = l.contextType, i.context = typeof c == "object" && c !== null ? st(c) : Aa, i.state = t.memoizedState, c = l.getDerivedStateFromProps, typeof c == "function" && (qs(
        t,
        l,
        c,
        a
      ), i.state = t.memoizedState), typeof l.getDerivedStateFromProps == "function" || typeof i.getSnapshotBeforeUpdate == "function" || typeof i.UNSAFE_componentWillMount != "function" && typeof i.componentWillMount != "function" || (c = i.state, typeof i.componentWillMount == "function" && i.componentWillMount(), typeof i.UNSAFE_componentWillMount == "function" && i.UNSAFE_componentWillMount(), c !== i.state && Bs.enqueueReplaceState(i, i.state, null), Tn(t, a, i, u), zn(), i.state = t.memoizedState), typeof i.componentDidMount == "function" && (t.flags |= 4194308), a = !0;
    } else if (e === null) {
      i = t.stateNode;
      var r = t.memoizedProps, v = oa(l, r);
      i.props = v;
      var C = i.context, Q = l.contextType;
      c = Aa, typeof Q == "object" && Q !== null && (c = st(Q));
      var $ = l.getDerivedStateFromProps;
      Q = typeof $ == "function" || typeof i.getSnapshotBeforeUpdate == "function", r = t.pendingProps !== r, Q || typeof i.UNSAFE_componentWillReceiveProps != "function" && typeof i.componentWillReceiveProps != "function" || (r || C !== c) && $f(
        t,
        i,
        a,
        c
      ), Ml = !1;
      var R = t.memoizedState;
      i.state = R, Tn(t, a, i, u), zn(), C = t.memoizedState, r || R !== C || Ml ? (typeof $ == "function" && (qs(
        t,
        l,
        $,
        a
      ), C = t.memoizedState), (v = Ml || kf(
        t,
        l,
        v,
        a,
        R,
        C,
        c
      )) ? (Q || typeof i.UNSAFE_componentWillMount != "function" && typeof i.componentWillMount != "function" || (typeof i.componentWillMount == "function" && i.componentWillMount(), typeof i.UNSAFE_componentWillMount == "function" && i.UNSAFE_componentWillMount()), typeof i.componentDidMount == "function" && (t.flags |= 4194308)) : (typeof i.componentDidMount == "function" && (t.flags |= 4194308), t.memoizedProps = a, t.memoizedState = C), i.props = a, i.state = C, i.context = c, a = v) : (typeof i.componentDidMount == "function" && (t.flags |= 4194308), a = !1);
    } else {
      i = t.stateNode, ms(e, t), c = t.memoizedProps, Q = oa(l, c), i.props = Q, $ = t.pendingProps, R = i.context, C = l.contextType, v = Aa, typeof C == "object" && C !== null && (v = st(C)), r = l.getDerivedStateFromProps, (C = typeof r == "function" || typeof i.getSnapshotBeforeUpdate == "function") || typeof i.UNSAFE_componentWillReceiveProps != "function" && typeof i.componentWillReceiveProps != "function" || (c !== $ || R !== v) && $f(
        t,
        i,
        a,
        v
      ), Ml = !1, R = t.memoizedState, i.state = R, Tn(t, a, i, u), zn();
      var B = t.memoizedState;
      c !== $ || R !== B || Ml || e !== null && e.dependencies !== null && _u(e.dependencies) ? (typeof r == "function" && (qs(
        t,
        l,
        r,
        a
      ), B = t.memoizedState), (Q = Ml || kf(
        t,
        l,
        Q,
        a,
        R,
        B,
        v
      ) || e !== null && e.dependencies !== null && _u(e.dependencies)) ? (C || typeof i.UNSAFE_componentWillUpdate != "function" && typeof i.componentWillUpdate != "function" || (typeof i.componentWillUpdate == "function" && i.componentWillUpdate(a, B, v), typeof i.UNSAFE_componentWillUpdate == "function" && i.UNSAFE_componentWillUpdate(
        a,
        B,
        v
      )), typeof i.componentDidUpdate == "function" && (t.flags |= 4), typeof i.getSnapshotBeforeUpdate == "function" && (t.flags |= 1024)) : (typeof i.componentDidUpdate != "function" || c === e.memoizedProps && R === e.memoizedState || (t.flags |= 4), typeof i.getSnapshotBeforeUpdate != "function" || c === e.memoizedProps && R === e.memoizedState || (t.flags |= 1024), t.memoizedProps = a, t.memoizedState = B), i.props = a, i.state = B, i.context = v, a = Q) : (typeof i.componentDidUpdate != "function" || c === e.memoizedProps && R === e.memoizedState || (t.flags |= 4), typeof i.getSnapshotBeforeUpdate != "function" || c === e.memoizedProps && R === e.memoizedState || (t.flags |= 1024), a = !1);
    }
    return i = a, Lu(e, t), a = (t.flags & 128) !== 0, i || a ? (i = t.stateNode, l = a && typeof l.getDerivedStateFromError != "function" ? null : i.render(), t.flags |= 1, e !== null && a ? (t.child = ra(
      t,
      e.child,
      null,
      u
    ), t.child = ra(
      t,
      null,
      l,
      u
    )) : ct(e, t, l, u), t.memoizedState = i.state, e = t.child) : e = rl(
      e,
      t,
      u
    ), e;
  }
  function fo(e, t, l, a) {
    return aa(), t.flags |= 256, ct(e, t, l, a), t.child;
  }
  var Ls = {
    dehydrated: null,
    treeContext: null,
    retryLane: 0,
    hydrationErrors: null
  };
  function Qs(e) {
    return { baseLanes: e, cachePool: Ir() };
  }
  function Zs(e, t, l) {
    return e = e !== null ? e.childLanes & ~l : 0, t && (e |= Ct), e;
  }
  function oo(e, t, l) {
    var a = t.pendingProps, u = !1, i = (t.flags & 128) !== 0, c;
    if ((c = i) || (c = e !== null && e.memoizedState === null ? !1 : (ke.current & 2) !== 0), c && (u = !0, t.flags &= -129), c = (t.flags & 32) !== 0, t.flags &= -33, e === null) {
      if (Te) {
        if (u ? Al(t) : Cl(), (e = Le) ? (e = gd(
          e,
          Gt
        ), e = e !== null && e.data !== "&" ? e : null, e !== null && (t.memoizedState = {
          dehydrated: e,
          treeContext: _l !== null ? { id: kt, overflow: $t } : null,
          retryLane: 536870912,
          hydrationErrors: null
        }, l = Zr(e), l.return = t, t.child = l, it = t, Le = null)) : e = null, e === null) throw Nl(t);
        return Ec(e) ? t.lanes = 32 : t.lanes = 536870912, null;
      }
      var r = a.children;
      return a = a.fallback, u ? (Cl(), u = t.mode, r = Qu(
        { mode: "hidden", children: r },
        u
      ), a = la(
        a,
        u,
        l,
        null
      ), r.return = t, a.return = t, r.sibling = a, t.child = r, a = t.child, a.memoizedState = Qs(l), a.childLanes = Zs(
        e,
        c,
        l
      ), t.memoizedState = Ls, Rn(null, a)) : (Al(t), ws(t, r));
    }
    var v = e.memoizedState;
    if (v !== null && (r = v.dehydrated, r !== null)) {
      if (i)
        t.flags & 256 ? (Al(t), t.flags &= -257, t = Vs(
          e,
          t,
          l
        )) : t.memoizedState !== null ? (Cl(), t.child = e.child, t.flags |= 128, t = null) : (Cl(), r = a.fallback, u = t.mode, a = Qu(
          { mode: "visible", children: a.children },
          u
        ), r = la(
          r,
          u,
          l,
          null
        ), r.flags |= 2, a.return = t, r.return = t, a.sibling = r, t.child = a, ra(
          t,
          e.child,
          null,
          l
        ), a = t.child, a.memoizedState = Qs(l), a.childLanes = Zs(
          e,
          c,
          l
        ), t.memoizedState = Ls, t = Rn(null, a));
      else if (Al(t), Ec(r)) {
        if (c = r.nextSibling && r.nextSibling.dataset, c) var C = c.dgst;
        c = C, a = Error(f(419)), a.stack = "", a.digest = c, jn({ value: a, source: null, stack: null }), t = Vs(
          e,
          t,
          l
        );
      } else if (Ie || Ra(e, t, l, !1), c = (l & e.childLanes) !== 0, Ie || c) {
        if (c = Xe, c !== null && (a = Fc(c, l), a !== 0 && a !== v.retryLane))
          throw v.retryLane = a, ta(e, a), _t(c, e, a), Gs;
        Nc(r) || Wu(), t = Vs(
          e,
          t,
          l
        );
      } else
        Nc(r) ? (t.flags |= 192, t.child = e.child, t = null) : (e = v.treeContext, Le = Lt(
          r.nextSibling
        ), it = t, Te = !0, Sl = null, Gt = !1, e !== null && Kr(t, e), t = ws(
          t,
          a.children
        ), t.flags |= 4096);
      return t;
    }
    return u ? (Cl(), r = a.fallback, u = t.mode, v = e.child, C = v.sibling, a = al(v, {
      mode: "hidden",
      children: a.children
    }), a.subtreeFlags = v.subtreeFlags & 65011712, C !== null ? r = al(
      C,
      r
    ) : (r = la(
      r,
      u,
      l,
      null
    ), r.flags |= 2), r.return = t, a.return = t, a.sibling = r, t.child = a, Rn(null, a), a = t.child, r = e.child.memoizedState, r === null ? r = Qs(l) : (u = r.cachePool, u !== null ? (v = Fe._currentValue, u = u.parent !== v ? { parent: v, pool: v } : u) : u = Ir(), r = {
      baseLanes: r.baseLanes | l,
      cachePool: u
    }), a.memoizedState = r, a.childLanes = Zs(
      e,
      c,
      l
    ), t.memoizedState = Ls, Rn(e.child, a)) : (Al(t), l = e.child, e = l.sibling, l = al(l, {
      mode: "visible",
      children: a.children
    }), l.return = t, l.sibling = null, e !== null && (c = t.deletions, c === null ? (t.deletions = [e], t.flags |= 16) : c.push(e)), t.child = l, t.memoizedState = null, l);
  }
  function ws(e, t) {
    return t = Qu(
      { mode: "visible", children: t },
      e.mode
    ), t.return = e, e.child = t;
  }
  function Qu(e, t) {
    return e = Mt(22, e, null, t), e.lanes = 0, e;
  }
  function Vs(e, t, l) {
    return ra(t, e.child, null, l), e = ws(
      t,
      t.pendingProps.children
    ), e.flags |= 2, t.memoizedState = null, e;
  }
  function mo(e, t, l) {
    e.lanes |= t;
    var a = e.alternate;
    a !== null && (a.lanes |= t), is(e.return, t, l);
  }
  function Ks(e, t, l, a, u, i) {
    var c = e.memoizedState;
    c === null ? e.memoizedState = {
      isBackwards: t,
      rendering: null,
      renderingStartTime: 0,
      last: a,
      tail: l,
      tailMode: u,
      treeForkCount: i
    } : (c.isBackwards = t, c.rendering = null, c.renderingStartTime = 0, c.last = a, c.tail = l, c.tailMode = u, c.treeForkCount = i);
  }
  function ho(e, t, l) {
    var a = t.pendingProps, u = a.revealOrder, i = a.tail;
    a = a.children;
    var c = ke.current, r = (c & 2) !== 0;
    if (r ? (c = c & 1 | 2, t.flags |= 128) : c &= 1, D(ke, c), ct(e, t, a, l), a = Te ? gn : 0, !r && e !== null && (e.flags & 128) !== 0)
      e: for (e = t.child; e !== null; ) {
        if (e.tag === 13)
          e.memoizedState !== null && mo(e, l, t);
        else if (e.tag === 19)
          mo(e, l, t);
        else if (e.child !== null) {
          e.child.return = e, e = e.child;
          continue;
        }
        if (e === t) break e;
        for (; e.sibling === null; ) {
          if (e.return === null || e.return === t)
            break e;
          e = e.return;
        }
        e.sibling.return = e.return, e = e.sibling;
      }
    switch (u) {
      case "forwards":
        for (l = t.child, u = null; l !== null; )
          e = l.alternate, e !== null && Cu(e) === null && (u = l), l = l.sibling;
        l = u, l === null ? (u = t.child, t.child = null) : (u = l.sibling, l.sibling = null), Ks(
          t,
          !1,
          u,
          l,
          i,
          a
        );
        break;
      case "backwards":
      case "unstable_legacy-backwards":
        for (l = null, u = t.child, t.child = null; u !== null; ) {
          if (e = u.alternate, e !== null && Cu(e) === null) {
            t.child = u;
            break;
          }
          e = u.sibling, u.sibling = l, l = u, u = e;
        }
        Ks(
          t,
          !0,
          l,
          null,
          i,
          a
        );
        break;
      case "together":
        Ks(
          t,
          !1,
          null,
          null,
          void 0,
          a
        );
        break;
      default:
        t.memoizedState = null;
    }
    return t.child;
  }
  function rl(e, t, l) {
    if (e !== null && (t.dependencies = e.dependencies), Rl |= t.lanes, (l & t.childLanes) === 0)
      if (e !== null) {
        if (Ra(
          e,
          t,
          l,
          !1
        ), (l & t.childLanes) === 0)
          return null;
      } else return null;
    if (e !== null && t.child !== e.child)
      throw Error(f(153));
    if (t.child !== null) {
      for (e = t.child, l = al(e, e.pendingProps), t.child = l, l.return = t; e.sibling !== null; )
        e = e.sibling, l = l.sibling = al(e, e.pendingProps), l.return = t;
      l.sibling = null;
    }
    return t.child;
  }
  function Js(e, t) {
    return (e.lanes & t) !== 0 ? !0 : (e = e.dependencies, !!(e !== null && _u(e)));
  }
  function Ah(e, t, l) {
    switch (t.tag) {
      case 3:
        Oe(t, t.stateNode.containerInfo), El(t, Fe, e.memoizedState.cache), aa();
        break;
      case 27:
      case 5:
        nt(t);
        break;
      case 4:
        Oe(t, t.stateNode.containerInfo);
        break;
      case 10:
        El(
          t,
          t.type,
          t.memoizedProps.value
        );
        break;
      case 31:
        if (t.memoizedState !== null)
          return t.flags |= 128, ys(t), null;
        break;
      case 13:
        var a = t.memoizedState;
        if (a !== null)
          return a.dehydrated !== null ? (Al(t), t.flags |= 128, null) : (l & t.child.childLanes) !== 0 ? oo(e, t, l) : (Al(t), e = rl(
            e,
            t,
            l
          ), e !== null ? e.sibling : null);
        Al(t);
        break;
      case 19:
        var u = (e.flags & 128) !== 0;
        if (a = (l & t.childLanes) !== 0, a || (Ra(
          e,
          t,
          l,
          !1
        ), a = (l & t.childLanes) !== 0), u) {
          if (a)
            return ho(
              e,
              t,
              l
            );
          t.flags |= 128;
        }
        if (u = t.memoizedState, u !== null && (u.rendering = null, u.tail = null, u.lastEffect = null), D(ke, ke.current), a) break;
        return null;
      case 22:
        return t.lanes = 0, uo(
          e,
          t,
          l,
          t.pendingProps
        );
      case 24:
        El(t, Fe, e.memoizedState.cache);
    }
    return rl(e, t, l);
  }
  function vo(e, t, l) {
    if (e !== null)
      if (e.memoizedProps !== t.pendingProps)
        Ie = !0;
      else {
        if (!Js(e, l) && (t.flags & 128) === 0)
          return Ie = !1, Ah(
            e,
            t,
            l
          );
        Ie = (e.flags & 131072) !== 0;
      }
    else
      Ie = !1, Te && (t.flags & 1048576) !== 0 && Vr(t, gn, t.index);
    switch (t.lanes = 0, t.tag) {
      case 16:
        e: {
          var a = t.pendingProps;
          if (e = sa(t.elementType), t.type = e, typeof e == "function")
            Ii(e) ? (a = oa(e, a), t.tag = 1, t = ro(
              null,
              t,
              e,
              a,
              l
            )) : (t.tag = 0, t = Xs(
              null,
              t,
              e,
              a,
              l
            ));
          else {
            if (e != null) {
              var u = e.$$typeof;
              if (u === W) {
                t.tag = 11, t = lo(
                  null,
                  t,
                  e,
                  a,
                  l
                );
                break e;
              } else if (u === P) {
                t.tag = 14, t = ao(
                  null,
                  t,
                  e,
                  a,
                  l
                );
                break e;
              }
            }
            throw t = q(e) || e, Error(f(306, t, ""));
          }
        }
        return t;
      case 0:
        return Xs(
          e,
          t,
          t.type,
          t.pendingProps,
          l
        );
      case 1:
        return a = t.type, u = oa(
          a,
          t.pendingProps
        ), ro(
          e,
          t,
          a,
          u,
          l
        );
      case 3:
        e: {
          if (Oe(
            t,
            t.stateNode.containerInfo
          ), e === null) throw Error(f(387));
          a = t.pendingProps;
          var i = t.memoizedState;
          u = i.element, ms(e, t), Tn(t, a, null, l);
          var c = t.memoizedState;
          if (a = c.cache, El(t, Fe, a), a !== i.cache && ss(
            t,
            [Fe],
            l,
            !0
          ), zn(), a = c.element, i.isDehydrated)
            if (i = {
              element: a,
              isDehydrated: !1,
              cache: c.cache
            }, t.updateQueue.baseState = i, t.memoizedState = i, t.flags & 256) {
              t = fo(
                e,
                t,
                a,
                l
              );
              break e;
            } else if (a !== u) {
              u = qt(
                Error(f(424)),
                t
              ), jn(u), t = fo(
                e,
                t,
                a,
                l
              );
              break e;
            } else {
              switch (e = t.stateNode.containerInfo, e.nodeType) {
                case 9:
                  e = e.body;
                  break;
                default:
                  e = e.nodeName === "HTML" ? e.ownerDocument.body : e;
              }
              for (Le = Lt(e.firstChild), it = t, Te = !0, Sl = null, Gt = !0, l = nf(
                t,
                null,
                a,
                l
              ), t.child = l; l; )
                l.flags = l.flags & -3 | 4096, l = l.sibling;
            }
          else {
            if (aa(), a === u) {
              t = rl(
                e,
                t,
                l
              );
              break e;
            }
            ct(e, t, a, l);
          }
          t = t.child;
        }
        return t;
      case 26:
        return Lu(e, t), e === null ? (l = Md(
          t.type,
          null,
          t.pendingProps,
          null
        )) ? t.memoizedState = l : Te || (l = t.type, e = t.pendingProps, a = ni(
          ce.current
        ).createElement(l), a[ut] = t, a[pt] = e, rt(a, l, e), lt(a), t.stateNode = a) : t.memoizedState = Md(
          t.type,
          e.memoizedProps,
          t.pendingProps,
          e.memoizedState
        ), null;
      case 27:
        return nt(t), e === null && Te && (a = t.stateNode = Sd(
          t.type,
          t.pendingProps,
          ce.current
        ), it = t, Gt = !0, u = Le, Yl(t.type) ? (Mc = u, Le = Lt(a.firstChild)) : Le = u), ct(
          e,
          t,
          t.pendingProps.children,
          l
        ), Lu(e, t), e === null && (t.flags |= 4194304), t.child;
      case 5:
        return e === null && Te && ((u = a = Le) && (a = u0(
          a,
          t.type,
          t.pendingProps,
          Gt
        ), a !== null ? (t.stateNode = a, it = t, Le = Lt(a.firstChild), Gt = !1, u = !0) : u = !1), u || Nl(t)), nt(t), u = t.type, i = t.pendingProps, c = e !== null ? e.memoizedProps : null, a = i.children, jc(u, i) ? a = null : c !== null && jc(u, c) && (t.flags |= 32), t.memoizedState !== null && (u = gs(
          e,
          t,
          gh,
          null,
          null,
          l
        ), kn._currentValue = u), Lu(e, t), ct(e, t, a, l), t.child;
      case 6:
        return e === null && Te && ((e = l = Le) && (l = i0(
          l,
          t.pendingProps,
          Gt
        ), l !== null ? (t.stateNode = l, it = t, Le = null, e = !0) : e = !1), e || Nl(t)), null;
      case 13:
        return oo(e, t, l);
      case 4:
        return Oe(
          t,
          t.stateNode.containerInfo
        ), a = t.pendingProps, e === null ? t.child = ra(
          t,
          null,
          a,
          l
        ) : ct(e, t, a, l), t.child;
      case 11:
        return lo(
          e,
          t,
          t.type,
          t.pendingProps,
          l
        );
      case 7:
        return ct(
          e,
          t,
          t.pendingProps,
          l
        ), t.child;
      case 8:
        return ct(
          e,
          t,
          t.pendingProps.children,
          l
        ), t.child;
      case 12:
        return ct(
          e,
          t,
          t.pendingProps.children,
          l
        ), t.child;
      case 10:
        return a = t.pendingProps, El(t, t.type, a.value), ct(e, t, a.children, l), t.child;
      case 9:
        return u = t.type._context, a = t.pendingProps.children, ua(t), u = st(u), a = a(u), t.flags |= 1, ct(e, t, a, l), t.child;
      case 14:
        return ao(
          e,
          t,
          t.type,
          t.pendingProps,
          l
        );
      case 15:
        return no(
          e,
          t,
          t.type,
          t.pendingProps,
          l
        );
      case 19:
        return ho(e, t, l);
      case 31:
        return Th(e, t, l);
      case 22:
        return uo(
          e,
          t,
          l,
          t.pendingProps
        );
      case 24:
        return ua(t), a = st(Fe), e === null ? (u = fs(), u === null && (u = Xe, i = cs(), u.pooledCache = i, i.refCount++, i !== null && (u.pooledCacheLanes |= l), u = i), t.memoizedState = { parent: a, cache: u }, ds(t), El(t, Fe, u)) : ((e.lanes & l) !== 0 && (ms(e, t), Tn(t, null, null, l), zn()), u = e.memoizedState, i = t.memoizedState, u.parent !== a ? (u = { parent: a, cache: a }, t.memoizedState = u, t.lanes === 0 && (t.memoizedState = t.updateQueue.baseState = u), El(t, Fe, a)) : (a = i.cache, El(t, Fe, a), a !== u.cache && ss(
          t,
          [Fe],
          l,
          !0
        ))), ct(
          e,
          t,
          t.pendingProps.children,
          l
        ), t.child;
      case 29:
        throw t.pendingProps;
    }
    throw Error(f(156, t.tag));
  }
  function fl(e) {
    e.flags |= 4;
  }
  function ks(e, t, l, a, u) {
    if ((t = (e.mode & 32) !== 0) && (t = !1), t) {
      if (e.flags |= 16777216, (u & 335544128) === u)
        if (e.stateNode.complete) e.flags |= 8192;
        else if (Qo()) e.flags |= 8192;
        else
          throw ca = Mu, os;
    } else e.flags &= -16777217;
  }
  function po(e, t) {
    if (t.type !== "stylesheet" || (t.state.loading & 4) !== 0)
      e.flags &= -16777217;
    else if (e.flags |= 16777216, !Od(t))
      if (Qo()) e.flags |= 8192;
      else
        throw ca = Mu, os;
  }
  function Zu(e, t) {
    t !== null && (e.flags |= 4), e.flags & 16384 && (t = e.tag !== 22 ? Jc() : 536870912, e.lanes |= t, Va |= t);
  }
  function Un(e, t) {
    if (!Te)
      switch (e.tailMode) {
        case "hidden":
          t = e.tail;
          for (var l = null; t !== null; )
            t.alternate !== null && (l = t), t = t.sibling;
          l === null ? e.tail = null : l.sibling = null;
          break;
        case "collapsed":
          l = e.tail;
          for (var a = null; l !== null; )
            l.alternate !== null && (a = l), l = l.sibling;
          a === null ? t || e.tail === null ? e.tail = null : e.tail.sibling = null : a.sibling = null;
      }
  }
  function Qe(e) {
    var t = e.alternate !== null && e.alternate.child === e.child, l = 0, a = 0;
    if (t)
      for (var u = e.child; u !== null; )
        l |= u.lanes | u.childLanes, a |= u.subtreeFlags & 65011712, a |= u.flags & 65011712, u.return = e, u = u.sibling;
    else
      for (u = e.child; u !== null; )
        l |= u.lanes | u.childLanes, a |= u.subtreeFlags, a |= u.flags, u.return = e, u = u.sibling;
    return e.subtreeFlags |= a, e.childLanes = l, t;
  }
  function Ch(e, t, l) {
    var a = t.pendingProps;
    switch (ls(t), t.tag) {
      case 16:
      case 15:
      case 0:
      case 11:
      case 7:
      case 8:
      case 12:
      case 9:
      case 14:
        return Qe(t), null;
      case 1:
        return Qe(t), null;
      case 3:
        return l = t.stateNode, a = null, e !== null && (a = e.memoizedState.cache), t.memoizedState.cache !== a && (t.flags |= 2048), il(Fe), _e(), l.pendingContext && (l.context = l.pendingContext, l.pendingContext = null), (e === null || e.child === null) && (Da(t) ? fl(t) : e === null || e.memoizedState.isDehydrated && (t.flags & 256) === 0 || (t.flags |= 1024, ns())), Qe(t), null;
      case 26:
        var u = t.type, i = t.memoizedState;
        return e === null ? (fl(t), i !== null ? (Qe(t), po(t, i)) : (Qe(t), ks(
          t,
          u,
          null,
          a,
          l
        ))) : i ? i !== e.memoizedState ? (fl(t), Qe(t), po(t, i)) : (Qe(t), t.flags &= -16777217) : (e = e.memoizedProps, e !== a && fl(t), Qe(t), ks(
          t,
          u,
          e,
          a,
          l
        )), null;
      case 27:
        if (ve(t), l = ce.current, u = t.type, e !== null && t.stateNode != null)
          e.memoizedProps !== a && fl(t);
        else {
          if (!a) {
            if (t.stateNode === null)
              throw Error(f(166));
            return Qe(t), null;
          }
          e = G.current, Da(t) ? Jr(t) : (e = Sd(u, a, l), t.stateNode = e, fl(t));
        }
        return Qe(t), null;
      case 5:
        if (ve(t), u = t.type, e !== null && t.stateNode != null)
          e.memoizedProps !== a && fl(t);
        else {
          if (!a) {
            if (t.stateNode === null)
              throw Error(f(166));
            return Qe(t), null;
          }
          if (i = G.current, Da(t))
            Jr(t);
          else {
            var c = ni(
              ce.current
            );
            switch (i) {
              case 1:
                i = c.createElementNS(
                  "http://www.w3.org/2000/svg",
                  u
                );
                break;
              case 2:
                i = c.createElementNS(
                  "http://www.w3.org/1998/Math/MathML",
                  u
                );
                break;
              default:
                switch (u) {
                  case "svg":
                    i = c.createElementNS(
                      "http://www.w3.org/2000/svg",
                      u
                    );
                    break;
                  case "math":
                    i = c.createElementNS(
                      "http://www.w3.org/1998/Math/MathML",
                      u
                    );
                    break;
                  case "script":
                    i = c.createElement("div"), i.innerHTML = "<script><\/script>", i = i.removeChild(
                      i.firstChild
                    );
                    break;
                  case "select":
                    i = typeof a.is == "string" ? c.createElement("select", {
                      is: a.is
                    }) : c.createElement("select"), a.multiple ? i.multiple = !0 : a.size && (i.size = a.size);
                    break;
                  default:
                    i = typeof a.is == "string" ? c.createElement(u, { is: a.is }) : c.createElement(u);
                }
            }
            i[ut] = t, i[pt] = a;
            e: for (c = t.child; c !== null; ) {
              if (c.tag === 5 || c.tag === 6)
                i.appendChild(c.stateNode);
              else if (c.tag !== 4 && c.tag !== 27 && c.child !== null) {
                c.child.return = c, c = c.child;
                continue;
              }
              if (c === t) break e;
              for (; c.sibling === null; ) {
                if (c.return === null || c.return === t)
                  break e;
                c = c.return;
              }
              c.sibling.return = c.return, c = c.sibling;
            }
            t.stateNode = i;
            e: switch (rt(i, u, a), u) {
              case "button":
              case "input":
              case "select":
              case "textarea":
                a = !!a.autoFocus;
                break e;
              case "img":
                a = !0;
                break e;
              default:
                a = !1;
            }
            a && fl(t);
          }
        }
        return Qe(t), ks(
          t,
          t.type,
          e === null ? null : e.memoizedProps,
          t.pendingProps,
          l
        ), null;
      case 6:
        if (e && t.stateNode != null)
          e.memoizedProps !== a && fl(t);
        else {
          if (typeof a != "string" && t.stateNode === null)
            throw Error(f(166));
          if (e = ce.current, Da(t)) {
            if (e = t.stateNode, l = t.memoizedProps, a = null, u = it, u !== null)
              switch (u.tag) {
                case 27:
                case 5:
                  a = u.memoizedProps;
              }
            e[ut] = t, e = !!(e.nodeValue === l || a !== null && a.suppressHydrationWarning === !0 || dd(e.nodeValue, l)), e || Nl(t, !0);
          } else
            e = ni(e).createTextNode(
              a
            ), e[ut] = t, t.stateNode = e;
        }
        return Qe(t), null;
      case 31:
        if (l = t.memoizedState, e === null || e.memoizedState !== null) {
          if (a = Da(t), l !== null) {
            if (e === null) {
              if (!a) throw Error(f(318));
              if (e = t.memoizedState, e = e !== null ? e.dehydrated : null, !e) throw Error(f(557));
              e[ut] = t;
            } else
              aa(), (t.flags & 128) === 0 && (t.memoizedState = null), t.flags |= 4;
            Qe(t), e = !1;
          } else
            l = ns(), e !== null && e.memoizedState !== null && (e.memoizedState.hydrationErrors = l), e = !0;
          if (!e)
            return t.flags & 256 ? (Tt(t), t) : (Tt(t), null);
          if ((t.flags & 128) !== 0)
            throw Error(f(558));
        }
        return Qe(t), null;
      case 13:
        if (a = t.memoizedState, e === null || e.memoizedState !== null && e.memoizedState.dehydrated !== null) {
          if (u = Da(t), a !== null && a.dehydrated !== null) {
            if (e === null) {
              if (!u) throw Error(f(318));
              if (u = t.memoizedState, u = u !== null ? u.dehydrated : null, !u) throw Error(f(317));
              u[ut] = t;
            } else
              aa(), (t.flags & 128) === 0 && (t.memoizedState = null), t.flags |= 4;
            Qe(t), u = !1;
          } else
            u = ns(), e !== null && e.memoizedState !== null && (e.memoizedState.hydrationErrors = u), u = !0;
          if (!u)
            return t.flags & 256 ? (Tt(t), t) : (Tt(t), null);
        }
        return Tt(t), (t.flags & 128) !== 0 ? (t.lanes = l, t) : (l = a !== null, e = e !== null && e.memoizedState !== null, l && (a = t.child, u = null, a.alternate !== null && a.alternate.memoizedState !== null && a.alternate.memoizedState.cachePool !== null && (u = a.alternate.memoizedState.cachePool.pool), i = null, a.memoizedState !== null && a.memoizedState.cachePool !== null && (i = a.memoizedState.cachePool.pool), i !== u && (a.flags |= 2048)), l !== e && l && (t.child.flags |= 8192), Zu(t, t.updateQueue), Qe(t), null);
      case 4:
        return _e(), e === null && pc(t.stateNode.containerInfo), Qe(t), null;
      case 10:
        return il(t.type), Qe(t), null;
      case 19:
        if (o(ke), a = t.memoizedState, a === null) return Qe(t), null;
        if (u = (t.flags & 128) !== 0, i = a.rendering, i === null)
          if (u) Un(a, !1);
          else {
            if (Je !== 0 || e !== null && (e.flags & 128) !== 0)
              for (e = t.child; e !== null; ) {
                if (i = Cu(e), i !== null) {
                  for (t.flags |= 128, Un(a, !1), e = i.updateQueue, t.updateQueue = e, Zu(t, e), t.subtreeFlags = 0, e = l, l = t.child; l !== null; )
                    Qr(l, e), l = l.sibling;
                  return D(
                    ke,
                    ke.current & 1 | 2
                  ), Te && nl(t, a.treeForkCount), t.child;
                }
                e = e.sibling;
              }
            a.tail !== null && mt() > ku && (t.flags |= 128, u = !0, Un(a, !1), t.lanes = 4194304);
          }
        else {
          if (!u)
            if (e = Cu(i), e !== null) {
              if (t.flags |= 128, u = !0, e = e.updateQueue, t.updateQueue = e, Zu(t, e), Un(a, !0), a.tail === null && a.tailMode === "hidden" && !i.alternate && !Te)
                return Qe(t), null;
            } else
              2 * mt() - a.renderingStartTime > ku && l !== 536870912 && (t.flags |= 128, u = !0, Un(a, !1), t.lanes = 4194304);
          a.isBackwards ? (i.sibling = t.child, t.child = i) : (e = a.last, e !== null ? e.sibling = i : t.child = i, a.last = i);
        }
        return a.tail !== null ? (e = a.tail, a.rendering = e, a.tail = e.sibling, a.renderingStartTime = mt(), e.sibling = null, l = ke.current, D(
          ke,
          u ? l & 1 | 2 : l & 1
        ), Te && nl(t, a.treeForkCount), e) : (Qe(t), null);
      case 22:
      case 23:
        return Tt(t), bs(), a = t.memoizedState !== null, e !== null ? e.memoizedState !== null !== a && (t.flags |= 8192) : a && (t.flags |= 8192), a ? (l & 536870912) !== 0 && (t.flags & 128) === 0 && (Qe(t), t.subtreeFlags & 6 && (t.flags |= 8192)) : Qe(t), l = t.updateQueue, l !== null && Zu(t, l.retryQueue), l = null, e !== null && e.memoizedState !== null && e.memoizedState.cachePool !== null && (l = e.memoizedState.cachePool.pool), a = null, t.memoizedState !== null && t.memoizedState.cachePool !== null && (a = t.memoizedState.cachePool.pool), a !== l && (t.flags |= 2048), e !== null && o(ia), null;
      case 24:
        return l = null, e !== null && (l = e.memoizedState.cache), t.memoizedState.cache !== l && (t.flags |= 2048), il(Fe), Qe(t), null;
      case 25:
        return null;
      case 30:
        return null;
    }
    throw Error(f(156, t.tag));
  }
  function Oh(e, t) {
    switch (ls(t), t.tag) {
      case 1:
        return e = t.flags, e & 65536 ? (t.flags = e & -65537 | 128, t) : null;
      case 3:
        return il(Fe), _e(), e = t.flags, (e & 65536) !== 0 && (e & 128) === 0 ? (t.flags = e & -65537 | 128, t) : null;
      case 26:
      case 27:
      case 5:
        return ve(t), null;
      case 31:
        if (t.memoizedState !== null) {
          if (Tt(t), t.alternate === null)
            throw Error(f(340));
          aa();
        }
        return e = t.flags, e & 65536 ? (t.flags = e & -65537 | 128, t) : null;
      case 13:
        if (Tt(t), e = t.memoizedState, e !== null && e.dehydrated !== null) {
          if (t.alternate === null)
            throw Error(f(340));
          aa();
        }
        return e = t.flags, e & 65536 ? (t.flags = e & -65537 | 128, t) : null;
      case 19:
        return o(ke), null;
      case 4:
        return _e(), null;
      case 10:
        return il(t.type), null;
      case 22:
      case 23:
        return Tt(t), bs(), e !== null && o(ia), e = t.flags, e & 65536 ? (t.flags = e & -65537 | 128, t) : null;
      case 24:
        return il(Fe), null;
      case 25:
        return null;
      default:
        return null;
    }
  }
  function bo(e, t) {
    switch (ls(t), t.tag) {
      case 3:
        il(Fe), _e();
        break;
      case 26:
      case 27:
      case 5:
        ve(t);
        break;
      case 4:
        _e();
        break;
      case 31:
        t.memoizedState !== null && Tt(t);
        break;
      case 13:
        Tt(t);
        break;
      case 19:
        o(ke);
        break;
      case 10:
        il(t.type);
        break;
      case 22:
      case 23:
        Tt(t), bs(), e !== null && o(ia);
        break;
      case 24:
        il(Fe);
    }
  }
  function Hn(e, t) {
    try {
      var l = t.updateQueue, a = l !== null ? l.lastEffect : null;
      if (a !== null) {
        var u = a.next;
        l = u;
        do {
          if ((l.tag & e) === e) {
            a = void 0;
            var i = l.create, c = l.inst;
            a = i(), c.destroy = a;
          }
          l = l.next;
        } while (l !== u);
      }
    } catch (r) {
      He(t, t.return, r);
    }
  }
  function Ol(e, t, l) {
    try {
      var a = t.updateQueue, u = a !== null ? a.lastEffect : null;
      if (u !== null) {
        var i = u.next;
        a = i;
        do {
          if ((a.tag & e) === e) {
            var c = a.inst, r = c.destroy;
            if (r !== void 0) {
              c.destroy = void 0, u = t;
              var v = l, C = r;
              try {
                C();
              } catch (Q) {
                He(
                  u,
                  v,
                  Q
                );
              }
            }
          }
          a = a.next;
        } while (a !== i);
      }
    } catch (Q) {
      He(t, t.return, Q);
    }
  }
  function yo(e) {
    var t = e.updateQueue;
    if (t !== null) {
      var l = e.stateNode;
      try {
        sf(t, l);
      } catch (a) {
        He(e, e.return, a);
      }
    }
  }
  function xo(e, t, l) {
    l.props = oa(
      e.type,
      e.memoizedProps
    ), l.state = e.memoizedState;
    try {
      l.componentWillUnmount();
    } catch (a) {
      He(e, t, a);
    }
  }
  function qn(e, t) {
    try {
      var l = e.ref;
      if (l !== null) {
        switch (e.tag) {
          case 26:
          case 27:
          case 5:
            var a = e.stateNode;
            break;
          case 30:
            a = e.stateNode;
            break;
          default:
            a = e.stateNode;
        }
        typeof l == "function" ? e.refCleanup = l(a) : l.current = a;
      }
    } catch (u) {
      He(e, t, u);
    }
  }
  function Ft(e, t) {
    var l = e.ref, a = e.refCleanup;
    if (l !== null)
      if (typeof a == "function")
        try {
          a();
        } catch (u) {
          He(e, t, u);
        } finally {
          e.refCleanup = null, e = e.alternate, e != null && (e.refCleanup = null);
        }
      else if (typeof l == "function")
        try {
          l(null);
        } catch (u) {
          He(e, t, u);
        }
      else l.current = null;
  }
  function go(e) {
    var t = e.type, l = e.memoizedProps, a = e.stateNode;
    try {
      e: switch (t) {
        case "button":
        case "input":
        case "select":
        case "textarea":
          l.autoFocus && a.focus();
          break e;
        case "img":
          l.src ? a.src = l.src : l.srcSet && (a.srcset = l.srcSet);
      }
    } catch (u) {
      He(e, e.return, u);
    }
  }
  function $s(e, t, l) {
    try {
      var a = e.stateNode;
      Ph(a, e.type, l, t), a[pt] = t;
    } catch (u) {
      He(e, e.return, u);
    }
  }
  function jo(e) {
    return e.tag === 5 || e.tag === 3 || e.tag === 26 || e.tag === 27 && Yl(e.type) || e.tag === 4;
  }
  function Fs(e) {
    e: for (; ; ) {
      for (; e.sibling === null; ) {
        if (e.return === null || jo(e.return)) return null;
        e = e.return;
      }
      for (e.sibling.return = e.return, e = e.sibling; e.tag !== 5 && e.tag !== 6 && e.tag !== 18; ) {
        if (e.tag === 27 && Yl(e.type) || e.flags & 2 || e.child === null || e.tag === 4) continue e;
        e.child.return = e, e = e.child;
      }
      if (!(e.flags & 2)) return e.stateNode;
    }
  }
  function Ws(e, t, l) {
    var a = e.tag;
    if (a === 5 || a === 6)
      e = e.stateNode, t ? (l.nodeType === 9 ? l.body : l.nodeName === "HTML" ? l.ownerDocument.body : l).insertBefore(e, t) : (t = l.nodeType === 9 ? l.body : l.nodeName === "HTML" ? l.ownerDocument.body : l, t.appendChild(e), l = l._reactRootContainer, l != null || t.onclick !== null || (t.onclick = tl));
    else if (a !== 4 && (a === 27 && Yl(e.type) && (l = e.stateNode, t = null), e = e.child, e !== null))
      for (Ws(e, t, l), e = e.sibling; e !== null; )
        Ws(e, t, l), e = e.sibling;
  }
  function wu(e, t, l) {
    var a = e.tag;
    if (a === 5 || a === 6)
      e = e.stateNode, t ? l.insertBefore(e, t) : l.appendChild(e);
    else if (a !== 4 && (a === 27 && Yl(e.type) && (l = e.stateNode), e = e.child, e !== null))
      for (wu(e, t, l), e = e.sibling; e !== null; )
        wu(e, t, l), e = e.sibling;
  }
  function _o(e) {
    var t = e.stateNode, l = e.memoizedProps;
    try {
      for (var a = e.type, u = t.attributes; u.length; )
        t.removeAttributeNode(u[0]);
      rt(t, a, l), t[ut] = e, t[pt] = l;
    } catch (i) {
      He(e, e.return, i);
    }
  }
  var ol = !1, Pe = !1, Is = !1, So = typeof WeakSet == "function" ? WeakSet : Set, at = null;
  function Dh(e, t) {
    if (e = e.containerInfo, xc = oi, e = Rr(e), Vi(e)) {
      if ("selectionStart" in e)
        var l = {
          start: e.selectionStart,
          end: e.selectionEnd
        };
      else
        e: {
          l = (l = e.ownerDocument) && l.defaultView || window;
          var a = l.getSelection && l.getSelection();
          if (a && a.rangeCount !== 0) {
            l = a.anchorNode;
            var u = a.anchorOffset, i = a.focusNode;
            a = a.focusOffset;
            try {
              l.nodeType, i.nodeType;
            } catch {
              l = null;
              break e;
            }
            var c = 0, r = -1, v = -1, C = 0, Q = 0, $ = e, R = null;
            t: for (; ; ) {
              for (var B; $ !== l || u !== 0 && $.nodeType !== 3 || (r = c + u), $ !== i || a !== 0 && $.nodeType !== 3 || (v = c + a), $.nodeType === 3 && (c += $.nodeValue.length), (B = $.firstChild) !== null; )
                R = $, $ = B;
              for (; ; ) {
                if ($ === e) break t;
                if (R === l && ++C === u && (r = c), R === i && ++Q === a && (v = c), (B = $.nextSibling) !== null) break;
                $ = R, R = $.parentNode;
              }
              $ = B;
            }
            l = r === -1 || v === -1 ? null : { start: r, end: v };
          } else l = null;
        }
      l = l || { start: 0, end: 0 };
    } else l = null;
    for (gc = { focusedElem: e, selectionRange: l }, oi = !1, at = t; at !== null; )
      if (t = at, e = t.child, (t.subtreeFlags & 1028) !== 0 && e !== null)
        e.return = t, at = e;
      else
        for (; at !== null; ) {
          switch (t = at, i = t.alternate, e = t.flags, t.tag) {
            case 0:
              if ((e & 4) !== 0 && (e = t.updateQueue, e = e !== null ? e.events : null, e !== null))
                for (l = 0; l < e.length; l++)
                  u = e[l], u.ref.impl = u.nextImpl;
              break;
            case 11:
            case 15:
              break;
            case 1:
              if ((e & 1024) !== 0 && i !== null) {
                e = void 0, l = t, u = i.memoizedProps, i = i.memoizedState, a = l.stateNode;
                try {
                  var se = oa(
                    l.type,
                    u
                  );
                  e = a.getSnapshotBeforeUpdate(
                    se,
                    i
                  ), a.__reactInternalSnapshotBeforeUpdate = e;
                } catch (he) {
                  He(
                    l,
                    l.return,
                    he
                  );
                }
              }
              break;
            case 3:
              if ((e & 1024) !== 0) {
                if (e = t.stateNode.containerInfo, l = e.nodeType, l === 9)
                  Sc(e);
                else if (l === 1)
                  switch (e.nodeName) {
                    case "HEAD":
                    case "HTML":
                    case "BODY":
                      Sc(e);
                      break;
                    default:
                      e.textContent = "";
                  }
              }
              break;
            case 5:
            case 26:
            case 27:
            case 6:
            case 4:
            case 17:
              break;
            default:
              if ((e & 1024) !== 0) throw Error(f(163));
          }
          if (e = t.sibling, e !== null) {
            e.return = t.return, at = e;
            break;
          }
          at = t.return;
        }
  }
  function No(e, t, l) {
    var a = l.flags;
    switch (l.tag) {
      case 0:
      case 11:
      case 15:
        ml(e, l), a & 4 && Hn(5, l);
        break;
      case 1:
        if (ml(e, l), a & 4)
          if (e = l.stateNode, t === null)
            try {
              e.componentDidMount();
            } catch (c) {
              He(l, l.return, c);
            }
          else {
            var u = oa(
              l.type,
              t.memoizedProps
            );
            t = t.memoizedState;
            try {
              e.componentDidUpdate(
                u,
                t,
                e.__reactInternalSnapshotBeforeUpdate
              );
            } catch (c) {
              He(
                l,
                l.return,
                c
              );
            }
          }
        a & 64 && yo(l), a & 512 && qn(l, l.return);
        break;
      case 3:
        if (ml(e, l), a & 64 && (e = l.updateQueue, e !== null)) {
          if (t = null, l.child !== null)
            switch (l.child.tag) {
              case 27:
              case 5:
                t = l.child.stateNode;
                break;
              case 1:
                t = l.child.stateNode;
            }
          try {
            sf(e, t);
          } catch (c) {
            He(l, l.return, c);
          }
        }
        break;
      case 27:
        t === null && a & 4 && _o(l);
      case 26:
      case 5:
        ml(e, l), t === null && a & 4 && go(l), a & 512 && qn(l, l.return);
        break;
      case 12:
        ml(e, l);
        break;
      case 31:
        ml(e, l), a & 4 && zo(e, l);
        break;
      case 13:
        ml(e, l), a & 4 && To(e, l), a & 64 && (e = l.memoizedState, e !== null && (e = e.dehydrated, e !== null && (l = Lh.bind(
          null,
          l
        ), s0(e, l))));
        break;
      case 22:
        if (a = l.memoizedState !== null || ol, !a) {
          t = t !== null && t.memoizedState !== null || Pe, u = ol;
          var i = Pe;
          ol = a, (Pe = t) && !i ? hl(
            e,
            l,
            (l.subtreeFlags & 8772) !== 0
          ) : ml(e, l), ol = u, Pe = i;
        }
        break;
      case 30:
        break;
      default:
        ml(e, l);
    }
  }
  function Eo(e) {
    var t = e.alternate;
    t !== null && (e.alternate = null, Eo(t)), e.child = null, e.deletions = null, e.sibling = null, e.tag === 5 && (t = e.stateNode, t !== null && Ti(t)), e.stateNode = null, e.return = null, e.dependencies = null, e.memoizedProps = null, e.memoizedState = null, e.pendingProps = null, e.stateNode = null, e.updateQueue = null;
  }
  var Ze = null, yt = !1;
  function dl(e, t, l) {
    for (l = l.child; l !== null; )
      Mo(e, t, l), l = l.sibling;
  }
  function Mo(e, t, l) {
    if (St && typeof St.onCommitFiberUnmount == "function")
      try {
        St.onCommitFiberUnmount(un, l);
      } catch {
      }
    switch (l.tag) {
      case 26:
        Pe || Ft(l, t), dl(
          e,
          t,
          l
        ), l.memoizedState ? l.memoizedState.count-- : l.stateNode && (l = l.stateNode, l.parentNode.removeChild(l));
        break;
      case 27:
        Pe || Ft(l, t);
        var a = Ze, u = yt;
        Yl(l.type) && (Ze = l.stateNode, yt = !1), dl(
          e,
          t,
          l
        ), Vn(l.stateNode), Ze = a, yt = u;
        break;
      case 5:
        Pe || Ft(l, t);
      case 6:
        if (a = Ze, u = yt, Ze = null, dl(
          e,
          t,
          l
        ), Ze = a, yt = u, Ze !== null)
          if (yt)
            try {
              (Ze.nodeType === 9 ? Ze.body : Ze.nodeName === "HTML" ? Ze.ownerDocument.body : Ze).removeChild(l.stateNode);
            } catch (i) {
              He(
                l,
                t,
                i
              );
            }
          else
            try {
              Ze.removeChild(l.stateNode);
            } catch (i) {
              He(
                l,
                t,
                i
              );
            }
        break;
      case 18:
        Ze !== null && (yt ? (e = Ze, yd(
          e.nodeType === 9 ? e.body : e.nodeName === "HTML" ? e.ownerDocument.body : e,
          l.stateNode
        ), Pa(e)) : yd(Ze, l.stateNode));
        break;
      case 4:
        a = Ze, u = yt, Ze = l.stateNode.containerInfo, yt = !0, dl(
          e,
          t,
          l
        ), Ze = a, yt = u;
        break;
      case 0:
      case 11:
      case 14:
      case 15:
        Ol(2, l, t), Pe || Ol(4, l, t), dl(
          e,
          t,
          l
        );
        break;
      case 1:
        Pe || (Ft(l, t), a = l.stateNode, typeof a.componentWillUnmount == "function" && xo(
          l,
          t,
          a
        )), dl(
          e,
          t,
          l
        );
        break;
      case 21:
        dl(
          e,
          t,
          l
        );
        break;
      case 22:
        Pe = (a = Pe) || l.memoizedState !== null, dl(
          e,
          t,
          l
        ), Pe = a;
        break;
      default:
        dl(
          e,
          t,
          l
        );
    }
  }
  function zo(e, t) {
    if (t.memoizedState === null && (e = t.alternate, e !== null && (e = e.memoizedState, e !== null))) {
      e = e.dehydrated;
      try {
        Pa(e);
      } catch (l) {
        He(t, t.return, l);
      }
    }
  }
  function To(e, t) {
    if (t.memoizedState === null && (e = t.alternate, e !== null && (e = e.memoizedState, e !== null && (e = e.dehydrated, e !== null))))
      try {
        Pa(e);
      } catch (l) {
        He(t, t.return, l);
      }
  }
  function Rh(e) {
    switch (e.tag) {
      case 31:
      case 13:
      case 19:
        var t = e.stateNode;
        return t === null && (t = e.stateNode = new So()), t;
      case 22:
        return e = e.stateNode, t = e._retryCache, t === null && (t = e._retryCache = new So()), t;
      default:
        throw Error(f(435, e.tag));
    }
  }
  function Vu(e, t) {
    var l = Rh(e);
    t.forEach(function(a) {
      if (!l.has(a)) {
        l.add(a);
        var u = Qh.bind(null, e, a);
        a.then(u, u);
      }
    });
  }
  function xt(e, t) {
    var l = t.deletions;
    if (l !== null)
      for (var a = 0; a < l.length; a++) {
        var u = l[a], i = e, c = t, r = c;
        e: for (; r !== null; ) {
          switch (r.tag) {
            case 27:
              if (Yl(r.type)) {
                Ze = r.stateNode, yt = !1;
                break e;
              }
              break;
            case 5:
              Ze = r.stateNode, yt = !1;
              break e;
            case 3:
            case 4:
              Ze = r.stateNode.containerInfo, yt = !0;
              break e;
          }
          r = r.return;
        }
        if (Ze === null) throw Error(f(160));
        Mo(i, c, u), Ze = null, yt = !1, i = u.alternate, i !== null && (i.return = null), u.return = null;
      }
    if (t.subtreeFlags & 13886)
      for (t = t.child; t !== null; )
        Ao(t, e), t = t.sibling;
  }
  var Vt = null;
  function Ao(e, t) {
    var l = e.alternate, a = e.flags;
    switch (e.tag) {
      case 0:
      case 11:
      case 14:
      case 15:
        xt(t, e), gt(e), a & 4 && (Ol(3, e, e.return), Hn(3, e), Ol(5, e, e.return));
        break;
      case 1:
        xt(t, e), gt(e), a & 512 && (Pe || l === null || Ft(l, l.return)), a & 64 && ol && (e = e.updateQueue, e !== null && (a = e.callbacks, a !== null && (l = e.shared.hiddenCallbacks, e.shared.hiddenCallbacks = l === null ? a : l.concat(a))));
        break;
      case 26:
        var u = Vt;
        if (xt(t, e), gt(e), a & 512 && (Pe || l === null || Ft(l, l.return)), a & 4) {
          var i = l !== null ? l.memoizedState : null;
          if (a = e.memoizedState, l === null)
            if (a === null)
              if (e.stateNode === null) {
                e: {
                  a = e.type, l = e.memoizedProps, u = u.ownerDocument || u;
                  t: switch (a) {
                    case "title":
                      i = u.getElementsByTagName("title")[0], (!i || i[rn] || i[ut] || i.namespaceURI === "http://www.w3.org/2000/svg" || i.hasAttribute("itemprop")) && (i = u.createElement(a), u.head.insertBefore(
                        i,
                        u.querySelector("head > title")
                      )), rt(i, a, l), i[ut] = e, lt(i), a = i;
                      break e;
                    case "link":
                      var c = Ad(
                        "link",
                        "href",
                        u
                      ).get(a + (l.href || ""));
                      if (c) {
                        for (var r = 0; r < c.length; r++)
                          if (i = c[r], i.getAttribute("href") === (l.href == null || l.href === "" ? null : l.href) && i.getAttribute("rel") === (l.rel == null ? null : l.rel) && i.getAttribute("title") === (l.title == null ? null : l.title) && i.getAttribute("crossorigin") === (l.crossOrigin == null ? null : l.crossOrigin)) {
                            c.splice(r, 1);
                            break t;
                          }
                      }
                      i = u.createElement(a), rt(i, a, l), u.head.appendChild(i);
                      break;
                    case "meta":
                      if (c = Ad(
                        "meta",
                        "content",
                        u
                      ).get(a + (l.content || ""))) {
                        for (r = 0; r < c.length; r++)
                          if (i = c[r], i.getAttribute("content") === (l.content == null ? null : "" + l.content) && i.getAttribute("name") === (l.name == null ? null : l.name) && i.getAttribute("property") === (l.property == null ? null : l.property) && i.getAttribute("http-equiv") === (l.httpEquiv == null ? null : l.httpEquiv) && i.getAttribute("charset") === (l.charSet == null ? null : l.charSet)) {
                            c.splice(r, 1);
                            break t;
                          }
                      }
                      i = u.createElement(a), rt(i, a, l), u.head.appendChild(i);
                      break;
                    default:
                      throw Error(f(468, a));
                  }
                  i[ut] = e, lt(i), a = i;
                }
                e.stateNode = a;
              } else
                Cd(
                  u,
                  e.type,
                  e.stateNode
                );
            else
              e.stateNode = Td(
                u,
                a,
                e.memoizedProps
              );
          else
            i !== a ? (i === null ? l.stateNode !== null && (l = l.stateNode, l.parentNode.removeChild(l)) : i.count--, a === null ? Cd(
              u,
              e.type,
              e.stateNode
            ) : Td(
              u,
              a,
              e.memoizedProps
            )) : a === null && e.stateNode !== null && $s(
              e,
              e.memoizedProps,
              l.memoizedProps
            );
        }
        break;
      case 27:
        xt(t, e), gt(e), a & 512 && (Pe || l === null || Ft(l, l.return)), l !== null && a & 4 && $s(
          e,
          e.memoizedProps,
          l.memoizedProps
        );
        break;
      case 5:
        if (xt(t, e), gt(e), a & 512 && (Pe || l === null || Ft(l, l.return)), e.flags & 32) {
          u = e.stateNode;
          try {
            _a(u, "");
          } catch (se) {
            He(e, e.return, se);
          }
        }
        a & 4 && e.stateNode != null && (u = e.memoizedProps, $s(
          e,
          u,
          l !== null ? l.memoizedProps : u
        )), a & 1024 && (Is = !0);
        break;
      case 6:
        if (xt(t, e), gt(e), a & 4) {
          if (e.stateNode === null)
            throw Error(f(162));
          a = e.memoizedProps, l = e.stateNode;
          try {
            l.nodeValue = a;
          } catch (se) {
            He(e, e.return, se);
          }
        }
        break;
      case 3:
        if (si = null, u = Vt, Vt = ui(t.containerInfo), xt(t, e), Vt = u, gt(e), a & 4 && l !== null && l.memoizedState.isDehydrated)
          try {
            Pa(t.containerInfo);
          } catch (se) {
            He(e, e.return, se);
          }
        Is && (Is = !1, Co(e));
        break;
      case 4:
        a = Vt, Vt = ui(
          e.stateNode.containerInfo
        ), xt(t, e), gt(e), Vt = a;
        break;
      case 12:
        xt(t, e), gt(e);
        break;
      case 31:
        xt(t, e), gt(e), a & 4 && (a = e.updateQueue, a !== null && (e.updateQueue = null, Vu(e, a)));
        break;
      case 13:
        xt(t, e), gt(e), e.child.flags & 8192 && e.memoizedState !== null != (l !== null && l.memoizedState !== null) && (Ju = mt()), a & 4 && (a = e.updateQueue, a !== null && (e.updateQueue = null, Vu(e, a)));
        break;
      case 22:
        u = e.memoizedState !== null;
        var v = l !== null && l.memoizedState !== null, C = ol, Q = Pe;
        if (ol = C || u, Pe = Q || v, xt(t, e), Pe = Q, ol = C, gt(e), a & 8192)
          e: for (t = e.stateNode, t._visibility = u ? t._visibility & -2 : t._visibility | 1, u && (l === null || v || ol || Pe || da(e)), l = null, t = e; ; ) {
            if (t.tag === 5 || t.tag === 26) {
              if (l === null) {
                v = l = t;
                try {
                  if (i = v.stateNode, u)
                    c = i.style, typeof c.setProperty == "function" ? c.setProperty("display", "none", "important") : c.display = "none";
                  else {
                    r = v.stateNode;
                    var $ = v.memoizedProps.style, R = $ != null && $.hasOwnProperty("display") ? $.display : null;
                    r.style.display = R == null || typeof R == "boolean" ? "" : ("" + R).trim();
                  }
                } catch (se) {
                  He(v, v.return, se);
                }
              }
            } else if (t.tag === 6) {
              if (l === null) {
                v = t;
                try {
                  v.stateNode.nodeValue = u ? "" : v.memoizedProps;
                } catch (se) {
                  He(v, v.return, se);
                }
              }
            } else if (t.tag === 18) {
              if (l === null) {
                v = t;
                try {
                  var B = v.stateNode;
                  u ? xd(B, !0) : xd(v.stateNode, !1);
                } catch (se) {
                  He(v, v.return, se);
                }
              }
            } else if ((t.tag !== 22 && t.tag !== 23 || t.memoizedState === null || t === e) && t.child !== null) {
              t.child.return = t, t = t.child;
              continue;
            }
            if (t === e) break e;
            for (; t.sibling === null; ) {
              if (t.return === null || t.return === e) break e;
              l === t && (l = null), t = t.return;
            }
            l === t && (l = null), t.sibling.return = t.return, t = t.sibling;
          }
        a & 4 && (a = e.updateQueue, a !== null && (l = a.retryQueue, l !== null && (a.retryQueue = null, Vu(e, l))));
        break;
      case 19:
        xt(t, e), gt(e), a & 4 && (a = e.updateQueue, a !== null && (e.updateQueue = null, Vu(e, a)));
        break;
      case 30:
        break;
      case 21:
        break;
      default:
        xt(t, e), gt(e);
    }
  }
  function gt(e) {
    var t = e.flags;
    if (t & 2) {
      try {
        for (var l, a = e.return; a !== null; ) {
          if (jo(a)) {
            l = a;
            break;
          }
          a = a.return;
        }
        if (l == null) throw Error(f(160));
        switch (l.tag) {
          case 27:
            var u = l.stateNode, i = Fs(e);
            wu(e, i, u);
            break;
          case 5:
            var c = l.stateNode;
            l.flags & 32 && (_a(c, ""), l.flags &= -33);
            var r = Fs(e);
            wu(e, r, c);
            break;
          case 3:
          case 4:
            var v = l.stateNode.containerInfo, C = Fs(e);
            Ws(
              e,
              C,
              v
            );
            break;
          default:
            throw Error(f(161));
        }
      } catch (Q) {
        He(e, e.return, Q);
      }
      e.flags &= -3;
    }
    t & 4096 && (e.flags &= -4097);
  }
  function Co(e) {
    if (e.subtreeFlags & 1024)
      for (e = e.child; e !== null; ) {
        var t = e;
        Co(t), t.tag === 5 && t.flags & 1024 && t.stateNode.reset(), e = e.sibling;
      }
  }
  function ml(e, t) {
    if (t.subtreeFlags & 8772)
      for (t = t.child; t !== null; )
        No(e, t.alternate, t), t = t.sibling;
  }
  function da(e) {
    for (e = e.child; e !== null; ) {
      var t = e;
      switch (t.tag) {
        case 0:
        case 11:
        case 14:
        case 15:
          Ol(4, t, t.return), da(t);
          break;
        case 1:
          Ft(t, t.return);
          var l = t.stateNode;
          typeof l.componentWillUnmount == "function" && xo(
            t,
            t.return,
            l
          ), da(t);
          break;
        case 27:
          Vn(t.stateNode);
        case 26:
        case 5:
          Ft(t, t.return), da(t);
          break;
        case 22:
          t.memoizedState === null && da(t);
          break;
        case 30:
          da(t);
          break;
        default:
          da(t);
      }
      e = e.sibling;
    }
  }
  function hl(e, t, l) {
    for (l = l && (t.subtreeFlags & 8772) !== 0, t = t.child; t !== null; ) {
      var a = t.alternate, u = e, i = t, c = i.flags;
      switch (i.tag) {
        case 0:
        case 11:
        case 15:
          hl(
            u,
            i,
            l
          ), Hn(4, i);
          break;
        case 1:
          if (hl(
            u,
            i,
            l
          ), a = i, u = a.stateNode, typeof u.componentDidMount == "function")
            try {
              u.componentDidMount();
            } catch (C) {
              He(a, a.return, C);
            }
          if (a = i, u = a.updateQueue, u !== null) {
            var r = a.stateNode;
            try {
              var v = u.shared.hiddenCallbacks;
              if (v !== null)
                for (u.shared.hiddenCallbacks = null, u = 0; u < v.length; u++)
                  uf(v[u], r);
            } catch (C) {
              He(a, a.return, C);
            }
          }
          l && c & 64 && yo(i), qn(i, i.return);
          break;
        case 27:
          _o(i);
        case 26:
        case 5:
          hl(
            u,
            i,
            l
          ), l && a === null && c & 4 && go(i), qn(i, i.return);
          break;
        case 12:
          hl(
            u,
            i,
            l
          );
          break;
        case 31:
          hl(
            u,
            i,
            l
          ), l && c & 4 && zo(u, i);
          break;
        case 13:
          hl(
            u,
            i,
            l
          ), l && c & 4 && To(u, i);
          break;
        case 22:
          i.memoizedState === null && hl(
            u,
            i,
            l
          ), qn(i, i.return);
          break;
        case 30:
          break;
        default:
          hl(
            u,
            i,
            l
          );
      }
      t = t.sibling;
    }
  }
  function Ps(e, t) {
    var l = null;
    e !== null && e.memoizedState !== null && e.memoizedState.cachePool !== null && (l = e.memoizedState.cachePool.pool), e = null, t.memoizedState !== null && t.memoizedState.cachePool !== null && (e = t.memoizedState.cachePool.pool), e !== l && (e != null && e.refCount++, l != null && _n(l));
  }
  function ec(e, t) {
    e = null, t.alternate !== null && (e = t.alternate.memoizedState.cache), t = t.memoizedState.cache, t !== e && (t.refCount++, e != null && _n(e));
  }
  function Kt(e, t, l, a) {
    if (t.subtreeFlags & 10256)
      for (t = t.child; t !== null; )
        Oo(
          e,
          t,
          l,
          a
        ), t = t.sibling;
  }
  function Oo(e, t, l, a) {
    var u = t.flags;
    switch (t.tag) {
      case 0:
      case 11:
      case 15:
        Kt(
          e,
          t,
          l,
          a
        ), u & 2048 && Hn(9, t);
        break;
      case 1:
        Kt(
          e,
          t,
          l,
          a
        );
        break;
      case 3:
        Kt(
          e,
          t,
          l,
          a
        ), u & 2048 && (e = null, t.alternate !== null && (e = t.alternate.memoizedState.cache), t = t.memoizedState.cache, t !== e && (t.refCount++, e != null && _n(e)));
        break;
      case 12:
        if (u & 2048) {
          Kt(
            e,
            t,
            l,
            a
          ), e = t.stateNode;
          try {
            var i = t.memoizedProps, c = i.id, r = i.onPostCommit;
            typeof r == "function" && r(
              c,
              t.alternate === null ? "mount" : "update",
              e.passiveEffectDuration,
              -0
            );
          } catch (v) {
            He(t, t.return, v);
          }
        } else
          Kt(
            e,
            t,
            l,
            a
          );
        break;
      case 31:
        Kt(
          e,
          t,
          l,
          a
        );
        break;
      case 13:
        Kt(
          e,
          t,
          l,
          a
        );
        break;
      case 23:
        break;
      case 22:
        i = t.stateNode, c = t.alternate, t.memoizedState !== null ? i._visibility & 2 ? Kt(
          e,
          t,
          l,
          a
        ) : Bn(e, t) : i._visibility & 2 ? Kt(
          e,
          t,
          l,
          a
        ) : (i._visibility |= 2, Qa(
          e,
          t,
          l,
          a,
          (t.subtreeFlags & 10256) !== 0 || !1
        )), u & 2048 && Ps(c, t);
        break;
      case 24:
        Kt(
          e,
          t,
          l,
          a
        ), u & 2048 && ec(t.alternate, t);
        break;
      default:
        Kt(
          e,
          t,
          l,
          a
        );
    }
  }
  function Qa(e, t, l, a, u) {
    for (u = u && ((t.subtreeFlags & 10256) !== 0 || !1), t = t.child; t !== null; ) {
      var i = e, c = t, r = l, v = a, C = c.flags;
      switch (c.tag) {
        case 0:
        case 11:
        case 15:
          Qa(
            i,
            c,
            r,
            v,
            u
          ), Hn(8, c);
          break;
        case 23:
          break;
        case 22:
          var Q = c.stateNode;
          c.memoizedState !== null ? Q._visibility & 2 ? Qa(
            i,
            c,
            r,
            v,
            u
          ) : Bn(
            i,
            c
          ) : (Q._visibility |= 2, Qa(
            i,
            c,
            r,
            v,
            u
          )), u && C & 2048 && Ps(
            c.alternate,
            c
          );
          break;
        case 24:
          Qa(
            i,
            c,
            r,
            v,
            u
          ), u && C & 2048 && ec(c.alternate, c);
          break;
        default:
          Qa(
            i,
            c,
            r,
            v,
            u
          );
      }
      t = t.sibling;
    }
  }
  function Bn(e, t) {
    if (t.subtreeFlags & 10256)
      for (t = t.child; t !== null; ) {
        var l = e, a = t, u = a.flags;
        switch (a.tag) {
          case 22:
            Bn(l, a), u & 2048 && Ps(
              a.alternate,
              a
            );
            break;
          case 24:
            Bn(l, a), u & 2048 && ec(a.alternate, a);
            break;
          default:
            Bn(l, a);
        }
        t = t.sibling;
      }
  }
  var Yn = 8192;
  function Za(e, t, l) {
    if (e.subtreeFlags & Yn)
      for (e = e.child; e !== null; )
        Do(
          e,
          t,
          l
        ), e = e.sibling;
  }
  function Do(e, t, l) {
    switch (e.tag) {
      case 26:
        Za(
          e,
          t,
          l
        ), e.flags & Yn && e.memoizedState !== null && x0(
          l,
          Vt,
          e.memoizedState,
          e.memoizedProps
        );
        break;
      case 5:
        Za(
          e,
          t,
          l
        );
        break;
      case 3:
      case 4:
        var a = Vt;
        Vt = ui(e.stateNode.containerInfo), Za(
          e,
          t,
          l
        ), Vt = a;
        break;
      case 22:
        e.memoizedState === null && (a = e.alternate, a !== null && a.memoizedState !== null ? (a = Yn, Yn = 16777216, Za(
          e,
          t,
          l
        ), Yn = a) : Za(
          e,
          t,
          l
        ));
        break;
      default:
        Za(
          e,
          t,
          l
        );
    }
  }
  function Ro(e) {
    var t = e.alternate;
    if (t !== null && (e = t.child, e !== null)) {
      t.child = null;
      do
        t = e.sibling, e.sibling = null, e = t;
      while (e !== null);
    }
  }
  function Gn(e) {
    var t = e.deletions;
    if ((e.flags & 16) !== 0) {
      if (t !== null)
        for (var l = 0; l < t.length; l++) {
          var a = t[l];
          at = a, Ho(
            a,
            e
          );
        }
      Ro(e);
    }
    if (e.subtreeFlags & 10256)
      for (e = e.child; e !== null; )
        Uo(e), e = e.sibling;
  }
  function Uo(e) {
    switch (e.tag) {
      case 0:
      case 11:
      case 15:
        Gn(e), e.flags & 2048 && Ol(9, e, e.return);
        break;
      case 3:
        Gn(e);
        break;
      case 12:
        Gn(e);
        break;
      case 22:
        var t = e.stateNode;
        e.memoizedState !== null && t._visibility & 2 && (e.return === null || e.return.tag !== 13) ? (t._visibility &= -3, Ku(e)) : Gn(e);
        break;
      default:
        Gn(e);
    }
  }
  function Ku(e) {
    var t = e.deletions;
    if ((e.flags & 16) !== 0) {
      if (t !== null)
        for (var l = 0; l < t.length; l++) {
          var a = t[l];
          at = a, Ho(
            a,
            e
          );
        }
      Ro(e);
    }
    for (e = e.child; e !== null; ) {
      switch (t = e, t.tag) {
        case 0:
        case 11:
        case 15:
          Ol(8, t, t.return), Ku(t);
          break;
        case 22:
          l = t.stateNode, l._visibility & 2 && (l._visibility &= -3, Ku(t));
          break;
        default:
          Ku(t);
      }
      e = e.sibling;
    }
  }
  function Ho(e, t) {
    for (; at !== null; ) {
      var l = at;
      switch (l.tag) {
        case 0:
        case 11:
        case 15:
          Ol(8, l, t);
          break;
        case 23:
        case 22:
          if (l.memoizedState !== null && l.memoizedState.cachePool !== null) {
            var a = l.memoizedState.cachePool.pool;
            a != null && a.refCount++;
          }
          break;
        case 24:
          _n(l.memoizedState.cache);
      }
      if (a = l.child, a !== null) a.return = l, at = a;
      else
        e: for (l = e; at !== null; ) {
          a = at;
          var u = a.sibling, i = a.return;
          if (Eo(a), a === l) {
            at = null;
            break e;
          }
          if (u !== null) {
            u.return = i, at = u;
            break e;
          }
          at = i;
        }
    }
  }
  var Uh = {
    getCacheForType: function(e) {
      var t = st(Fe), l = t.data.get(e);
      return l === void 0 && (l = e(), t.data.set(e, l)), l;
    },
    cacheSignal: function() {
      return st(Fe).controller.signal;
    }
  }, Hh = typeof WeakMap == "function" ? WeakMap : Map, De = 0, Xe = null, Ne = null, Me = 0, Ue = 0, At = null, Dl = !1, wa = !1, tc = !1, vl = 0, Je = 0, Rl = 0, ma = 0, lc = 0, Ct = 0, Va = 0, Xn = null, jt = null, ac = !1, Ju = 0, qo = 0, ku = 1 / 0, $u = null, Ul = null, et = 0, Hl = null, Ka = null, pl = 0, nc = 0, uc = null, Bo = null, Ln = 0, ic = null;
  function Ot() {
    return (De & 2) !== 0 && Me !== 0 ? Me & -Me : S.T !== null ? dc() : Wc();
  }
  function Yo() {
    if (Ct === 0)
      if ((Me & 536870912) === 0 || Te) {
        var e = au;
        au <<= 1, (au & 3932160) === 0 && (au = 262144), Ct = e;
      } else Ct = 536870912;
    return e = zt.current, e !== null && (e.flags |= 32), Ct;
  }
  function _t(e, t, l) {
    (e === Xe && (Ue === 2 || Ue === 9) || e.cancelPendingCommit !== null) && (Ja(e, 0), ql(
      e,
      Me,
      Ct,
      !1
    )), cn(e, l), ((De & 2) === 0 || e !== Xe) && (e === Xe && ((De & 2) === 0 && (ma |= l), Je === 4 && ql(
      e,
      Me,
      Ct,
      !1
    )), Wt(e));
  }
  function Go(e, t, l) {
    if ((De & 6) !== 0) throw Error(f(327));
    var a = !l && (t & 127) === 0 && (t & e.expiredLanes) === 0 || sn(e, t), u = a ? Yh(e, t) : cc(e, t, !0), i = a;
    do {
      if (u === 0) {
        wa && !a && ql(e, t, 0, !1);
        break;
      } else {
        if (l = e.current.alternate, i && !qh(l)) {
          u = cc(e, t, !1), i = !1;
          continue;
        }
        if (u === 2) {
          if (i = t, e.errorRecoveryDisabledLanes & i)
            var c = 0;
          else
            c = e.pendingLanes & -536870913, c = c !== 0 ? c : c & 536870912 ? 536870912 : 0;
          if (c !== 0) {
            t = c;
            e: {
              var r = e;
              u = Xn;
              var v = r.current.memoizedState.isDehydrated;
              if (v && (Ja(r, c).flags |= 256), c = cc(
                r,
                c,
                !1
              ), c !== 2) {
                if (tc && !v) {
                  r.errorRecoveryDisabledLanes |= i, ma |= i, u = 4;
                  break e;
                }
                i = jt, jt = u, i !== null && (jt === null ? jt = i : jt.push.apply(
                  jt,
                  i
                ));
              }
              u = c;
            }
            if (i = !1, u !== 2) continue;
          }
        }
        if (u === 1) {
          Ja(e, 0), ql(e, t, 0, !0);
          break;
        }
        e: {
          switch (a = e, i = u, i) {
            case 0:
            case 1:
              throw Error(f(345));
            case 4:
              if ((t & 4194048) !== t) break;
            case 6:
              ql(
                a,
                t,
                Ct,
                !Dl
              );
              break e;
            case 2:
              jt = null;
              break;
            case 3:
            case 5:
              break;
            default:
              throw Error(f(329));
          }
          if ((t & 62914560) === t && (u = Ju + 300 - mt(), 10 < u)) {
            if (ql(
              a,
              t,
              Ct,
              !Dl
            ), uu(a, 0, !0) !== 0) break e;
            pl = t, a.timeoutHandle = pd(
              Xo.bind(
                null,
                a,
                l,
                jt,
                $u,
                ac,
                t,
                Ct,
                ma,
                Va,
                Dl,
                i,
                "Throttled",
                -0,
                0
              ),
              u
            );
            break e;
          }
          Xo(
            a,
            l,
            jt,
            $u,
            ac,
            t,
            Ct,
            ma,
            Va,
            Dl,
            i,
            null,
            -0,
            0
          );
        }
      }
      break;
    } while (!0);
    Wt(e);
  }
  function Xo(e, t, l, a, u, i, c, r, v, C, Q, $, R, B) {
    if (e.timeoutHandle = -1, $ = t.subtreeFlags, $ & 8192 || ($ & 16785408) === 16785408) {
      $ = {
        stylesheets: null,
        count: 0,
        imgCount: 0,
        imgBytes: 0,
        suspenseyImages: [],
        waitingForImages: !0,
        waitingForViewTransition: !1,
        unsuspend: tl
      }, Do(
        t,
        i,
        $
      );
      var se = (i & 62914560) === i ? Ju - mt() : (i & 4194048) === i ? qo - mt() : 0;
      if (se = g0(
        $,
        se
      ), se !== null) {
        pl = i, e.cancelPendingCommit = se(
          ko.bind(
            null,
            e,
            t,
            i,
            l,
            a,
            u,
            c,
            r,
            v,
            Q,
            $,
            null,
            R,
            B
          )
        ), ql(e, i, c, !C);
        return;
      }
    }
    ko(
      e,
      t,
      i,
      l,
      a,
      u,
      c,
      r,
      v
    );
  }
  function qh(e) {
    for (var t = e; ; ) {
      var l = t.tag;
      if ((l === 0 || l === 11 || l === 15) && t.flags & 16384 && (l = t.updateQueue, l !== null && (l = l.stores, l !== null)))
        for (var a = 0; a < l.length; a++) {
          var u = l[a], i = u.getSnapshot;
          u = u.value;
          try {
            if (!Et(i(), u)) return !1;
          } catch {
            return !1;
          }
        }
      if (l = t.child, t.subtreeFlags & 16384 && l !== null)
        l.return = t, t = l;
      else {
        if (t === e) break;
        for (; t.sibling === null; ) {
          if (t.return === null || t.return === e) return !0;
          t = t.return;
        }
        t.sibling.return = t.return, t = t.sibling;
      }
    }
    return !0;
  }
  function ql(e, t, l, a) {
    t &= ~lc, t &= ~ma, e.suspendedLanes |= t, e.pingedLanes &= ~t, a && (e.warmLanes |= t), a = e.expirationTimes;
    for (var u = t; 0 < u; ) {
      var i = 31 - Nt(u), c = 1 << i;
      a[i] = -1, u &= ~c;
    }
    l !== 0 && kc(e, l, t);
  }
  function Fu() {
    return (De & 6) === 0 ? (Qn(0), !1) : !0;
  }
  function sc() {
    if (Ne !== null) {
      if (Ue === 0)
        var e = Ne.return;
      else
        e = Ne, ul = na = null, Ss(e), Ba = null, Nn = 0, e = Ne;
      for (; e !== null; )
        bo(e.alternate, e), e = e.return;
      Ne = null;
    }
  }
  function Ja(e, t) {
    var l = e.timeoutHandle;
    l !== -1 && (e.timeoutHandle = -1, l0(l)), l = e.cancelPendingCommit, l !== null && (e.cancelPendingCommit = null, l()), pl = 0, sc(), Xe = e, Ne = l = al(e.current, null), Me = t, Ue = 0, At = null, Dl = !1, wa = sn(e, t), tc = !1, Va = Ct = lc = ma = Rl = Je = 0, jt = Xn = null, ac = !1, (t & 8) !== 0 && (t |= t & 32);
    var a = e.entangledLanes;
    if (a !== 0)
      for (e = e.entanglements, a &= t; 0 < a; ) {
        var u = 31 - Nt(a), i = 1 << u;
        t |= e[u], a &= ~i;
      }
    return vl = t, bu(), l;
  }
  function Lo(e, t) {
    xe = null, S.H = Dn, t === qa || t === Eu ? (t = tf(), Ue = 3) : t === os ? (t = tf(), Ue = 4) : Ue = t === Gs ? 8 : t !== null && typeof t == "object" && typeof t.then == "function" ? 6 : 1, At = t, Ne === null && (Je = 1, Gu(
      e,
      qt(t, e.current)
    ));
  }
  function Qo() {
    var e = zt.current;
    return e === null ? !0 : (Me & 4194048) === Me ? Xt === null : (Me & 62914560) === Me || (Me & 536870912) !== 0 ? e === Xt : !1;
  }
  function Zo() {
    var e = S.H;
    return S.H = Dn, e === null ? Dn : e;
  }
  function wo() {
    var e = S.A;
    return S.A = Uh, e;
  }
  function Wu() {
    Je = 4, Dl || (Me & 4194048) !== Me && zt.current !== null || (wa = !0), (Rl & 134217727) === 0 && (ma & 134217727) === 0 || Xe === null || ql(
      Xe,
      Me,
      Ct,
      !1
    );
  }
  function cc(e, t, l) {
    var a = De;
    De |= 2;
    var u = Zo(), i = wo();
    (Xe !== e || Me !== t) && ($u = null, Ja(e, t)), t = !1;
    var c = Je;
    e: do
      try {
        if (Ue !== 0 && Ne !== null) {
          var r = Ne, v = At;
          switch (Ue) {
            case 8:
              sc(), c = 6;
              break e;
            case 3:
            case 2:
            case 9:
            case 6:
              zt.current === null && (t = !0);
              var C = Ue;
              if (Ue = 0, At = null, ka(e, r, v, C), l && wa) {
                c = 0;
                break e;
              }
              break;
            default:
              C = Ue, Ue = 0, At = null, ka(e, r, v, C);
          }
        }
        Bh(), c = Je;
        break;
      } catch (Q) {
        Lo(e, Q);
      }
    while (!0);
    return t && e.shellSuspendCounter++, ul = na = null, De = a, S.H = u, S.A = i, Ne === null && (Xe = null, Me = 0, bu()), c;
  }
  function Bh() {
    for (; Ne !== null; ) Vo(Ne);
  }
  function Yh(e, t) {
    var l = De;
    De |= 2;
    var a = Zo(), u = wo();
    Xe !== e || Me !== t ? ($u = null, ku = mt() + 500, Ja(e, t)) : wa = sn(
      e,
      t
    );
    e: do
      try {
        if (Ue !== 0 && Ne !== null) {
          t = Ne;
          var i = At;
          t: switch (Ue) {
            case 1:
              Ue = 0, At = null, ka(e, t, i, 1);
              break;
            case 2:
            case 9:
              if (Pr(i)) {
                Ue = 0, At = null, Ko(t);
                break;
              }
              t = function() {
                Ue !== 2 && Ue !== 9 || Xe !== e || (Ue = 7), Wt(e);
              }, i.then(t, t);
              break e;
            case 3:
              Ue = 7;
              break e;
            case 4:
              Ue = 5;
              break e;
            case 7:
              Pr(i) ? (Ue = 0, At = null, Ko(t)) : (Ue = 0, At = null, ka(e, t, i, 7));
              break;
            case 5:
              var c = null;
              switch (Ne.tag) {
                case 26:
                  c = Ne.memoizedState;
                case 5:
                case 27:
                  var r = Ne;
                  if (c ? Od(c) : r.stateNode.complete) {
                    Ue = 0, At = null;
                    var v = r.sibling;
                    if (v !== null) Ne = v;
                    else {
                      var C = r.return;
                      C !== null ? (Ne = C, Iu(C)) : Ne = null;
                    }
                    break t;
                  }
              }
              Ue = 0, At = null, ka(e, t, i, 5);
              break;
            case 6:
              Ue = 0, At = null, ka(e, t, i, 6);
              break;
            case 8:
              sc(), Je = 6;
              break e;
            default:
              throw Error(f(462));
          }
        }
        Gh();
        break;
      } catch (Q) {
        Lo(e, Q);
      }
    while (!0);
    return ul = na = null, S.H = a, S.A = u, De = l, Ne !== null ? 0 : (Xe = null, Me = 0, bu(), Je);
  }
  function Gh() {
    for (; Ne !== null && !yl(); )
      Vo(Ne);
  }
  function Vo(e) {
    var t = vo(e.alternate, e, vl);
    e.memoizedProps = e.pendingProps, t === null ? Iu(e) : Ne = t;
  }
  function Ko(e) {
    var t = e, l = t.alternate;
    switch (t.tag) {
      case 15:
      case 0:
        t = co(
          l,
          t,
          t.pendingProps,
          t.type,
          void 0,
          Me
        );
        break;
      case 11:
        t = co(
          l,
          t,
          t.pendingProps,
          t.type.render,
          t.ref,
          Me
        );
        break;
      case 5:
        Ss(t);
      default:
        bo(l, t), t = Ne = Qr(t, vl), t = vo(l, t, vl);
    }
    e.memoizedProps = e.pendingProps, t === null ? Iu(e) : Ne = t;
  }
  function ka(e, t, l, a) {
    ul = na = null, Ss(t), Ba = null, Nn = 0;
    var u = t.return;
    try {
      if (zh(
        e,
        u,
        t,
        l,
        Me
      )) {
        Je = 1, Gu(
          e,
          qt(l, e.current)
        ), Ne = null;
        return;
      }
    } catch (i) {
      if (u !== null) throw Ne = u, i;
      Je = 1, Gu(
        e,
        qt(l, e.current)
      ), Ne = null;
      return;
    }
    t.flags & 32768 ? (Te || a === 1 ? e = !0 : wa || (Me & 536870912) !== 0 ? e = !1 : (Dl = e = !0, (a === 2 || a === 9 || a === 3 || a === 6) && (a = zt.current, a !== null && a.tag === 13 && (a.flags |= 16384))), Jo(t, e)) : Iu(t);
  }
  function Iu(e) {
    var t = e;
    do {
      if ((t.flags & 32768) !== 0) {
        Jo(
          t,
          Dl
        );
        return;
      }
      e = t.return;
      var l = Ch(
        t.alternate,
        t,
        vl
      );
      if (l !== null) {
        Ne = l;
        return;
      }
      if (t = t.sibling, t !== null) {
        Ne = t;
        return;
      }
      Ne = t = e;
    } while (t !== null);
    Je === 0 && (Je = 5);
  }
  function Jo(e, t) {
    do {
      var l = Oh(e.alternate, e);
      if (l !== null) {
        l.flags &= 32767, Ne = l;
        return;
      }
      if (l = e.return, l !== null && (l.flags |= 32768, l.subtreeFlags = 0, l.deletions = null), !t && (e = e.sibling, e !== null)) {
        Ne = e;
        return;
      }
      Ne = e = l;
    } while (e !== null);
    Je = 6, Ne = null;
  }
  function ko(e, t, l, a, u, i, c, r, v) {
    e.cancelPendingCommit = null;
    do
      Pu();
    while (et !== 0);
    if ((De & 6) !== 0) throw Error(f(327));
    if (t !== null) {
      if (t === e.current) throw Error(f(177));
      if (i = t.lanes | t.childLanes, i |= Fi, ym(
        e,
        l,
        i,
        c,
        r,
        v
      ), e === Xe && (Ne = Xe = null, Me = 0), Ka = t, Hl = e, pl = l, nc = i, uc = u, Bo = a, (t.subtreeFlags & 10256) !== 0 || (t.flags & 10256) !== 0 ? (e.callbackNode = null, e.callbackPriority = 0, Zh(Pt, function() {
        return Po(), null;
      })) : (e.callbackNode = null, e.callbackPriority = 0), a = (t.flags & 13878) !== 0, (t.subtreeFlags & 13878) !== 0 || a) {
        a = S.T, S.T = null, u = Z.p, Z.p = 2, c = De, De |= 4;
        try {
          Dh(e, t, l);
        } finally {
          De = c, Z.p = u, S.T = a;
        }
      }
      et = 1, $o(), Fo(), Wo();
    }
  }
  function $o() {
    if (et === 1) {
      et = 0;
      var e = Hl, t = Ka, l = (t.flags & 13878) !== 0;
      if ((t.subtreeFlags & 13878) !== 0 || l) {
        l = S.T, S.T = null;
        var a = Z.p;
        Z.p = 2;
        var u = De;
        De |= 4;
        try {
          Ao(t, e);
          var i = gc, c = Rr(e.containerInfo), r = i.focusedElem, v = i.selectionRange;
          if (c !== r && r && r.ownerDocument && Dr(
            r.ownerDocument.documentElement,
            r
          )) {
            if (v !== null && Vi(r)) {
              var C = v.start, Q = v.end;
              if (Q === void 0 && (Q = C), "selectionStart" in r)
                r.selectionStart = C, r.selectionEnd = Math.min(
                  Q,
                  r.value.length
                );
              else {
                var $ = r.ownerDocument || document, R = $ && $.defaultView || window;
                if (R.getSelection) {
                  var B = R.getSelection(), se = r.textContent.length, he = Math.min(v.start, se), Ge = v.end === void 0 ? he : Math.min(v.end, se);
                  !B.extend && he > Ge && (c = Ge, Ge = he, he = c);
                  var N = Or(
                    r,
                    he
                  ), g = Or(
                    r,
                    Ge
                  );
                  if (N && g && (B.rangeCount !== 1 || B.anchorNode !== N.node || B.anchorOffset !== N.offset || B.focusNode !== g.node || B.focusOffset !== g.offset)) {
                    var A = $.createRange();
                    A.setStart(N.node, N.offset), B.removeAllRanges(), he > Ge ? (B.addRange(A), B.extend(g.node, g.offset)) : (A.setEnd(g.node, g.offset), B.addRange(A));
                  }
                }
              }
            }
            for ($ = [], B = r; B = B.parentNode; )
              B.nodeType === 1 && $.push({
                element: B,
                left: B.scrollLeft,
                top: B.scrollTop
              });
            for (typeof r.focus == "function" && r.focus(), r = 0; r < $.length; r++) {
              var K = $[r];
              K.element.scrollLeft = K.left, K.element.scrollTop = K.top;
            }
          }
          oi = !!xc, gc = xc = null;
        } finally {
          De = u, Z.p = a, S.T = l;
        }
      }
      e.current = t, et = 2;
    }
  }
  function Fo() {
    if (et === 2) {
      et = 0;
      var e = Hl, t = Ka, l = (t.flags & 8772) !== 0;
      if ((t.subtreeFlags & 8772) !== 0 || l) {
        l = S.T, S.T = null;
        var a = Z.p;
        Z.p = 2;
        var u = De;
        De |= 4;
        try {
          No(e, t.alternate, t);
        } finally {
          De = u, Z.p = a, S.T = l;
        }
      }
      et = 3;
    }
  }
  function Wo() {
    if (et === 4 || et === 3) {
      et = 0, Si();
      var e = Hl, t = Ka, l = pl, a = Bo;
      (t.subtreeFlags & 10256) !== 0 || (t.flags & 10256) !== 0 ? et = 5 : (et = 0, Ka = Hl = null, Io(e, e.pendingLanes));
      var u = e.pendingLanes;
      if (u === 0 && (Ul = null), Mi(l), t = t.stateNode, St && typeof St.onCommitFiberRoot == "function")
        try {
          St.onCommitFiberRoot(
            un,
            t,
            void 0,
            (t.current.flags & 128) === 128
          );
        } catch {
        }
      if (a !== null) {
        t = S.T, u = Z.p, Z.p = 2, S.T = null;
        try {
          for (var i = e.onRecoverableError, c = 0; c < a.length; c++) {
            var r = a[c];
            i(r.value, {
              componentStack: r.stack
            });
          }
        } finally {
          S.T = t, Z.p = u;
        }
      }
      (pl & 3) !== 0 && Pu(), Wt(e), u = e.pendingLanes, (l & 261930) !== 0 && (u & 42) !== 0 ? e === ic ? Ln++ : (Ln = 0, ic = e) : Ln = 0, Qn(0);
    }
  }
  function Io(e, t) {
    (e.pooledCacheLanes &= t) === 0 && (t = e.pooledCache, t != null && (e.pooledCache = null, _n(t)));
  }
  function Pu() {
    return $o(), Fo(), Wo(), Po();
  }
  function Po() {
    if (et !== 5) return !1;
    var e = Hl, t = nc;
    nc = 0;
    var l = Mi(pl), a = S.T, u = Z.p;
    try {
      Z.p = 32 > l ? 32 : l, S.T = null, l = uc, uc = null;
      var i = Hl, c = pl;
      if (et = 0, Ka = Hl = null, pl = 0, (De & 6) !== 0) throw Error(f(331));
      var r = De;
      if (De |= 4, Uo(i.current), Oo(
        i,
        i.current,
        c,
        l
      ), De = r, Qn(0, !1), St && typeof St.onPostCommitFiberRoot == "function")
        try {
          St.onPostCommitFiberRoot(un, i);
        } catch {
        }
      return !0;
    } finally {
      Z.p = u, S.T = a, Io(e, t);
    }
  }
  function ed(e, t, l) {
    t = qt(l, t), t = Ys(e.stateNode, t, 2), e = Tl(e, t, 2), e !== null && (cn(e, 2), Wt(e));
  }
  function He(e, t, l) {
    if (e.tag === 3)
      ed(e, e, l);
    else
      for (; t !== null; ) {
        if (t.tag === 3) {
          ed(
            t,
            e,
            l
          );
          break;
        } else if (t.tag === 1) {
          var a = t.stateNode;
          if (typeof t.type.getDerivedStateFromError == "function" || typeof a.componentDidCatch == "function" && (Ul === null || !Ul.has(a))) {
            e = qt(l, e), l = eo(2), a = Tl(t, l, 2), a !== null && (to(
              l,
              a,
              t,
              e
            ), cn(a, 2), Wt(a));
            break;
          }
        }
        t = t.return;
      }
  }
  function rc(e, t, l) {
    var a = e.pingCache;
    if (a === null) {
      a = e.pingCache = new Hh();
      var u = /* @__PURE__ */ new Set();
      a.set(t, u);
    } else
      u = a.get(t), u === void 0 && (u = /* @__PURE__ */ new Set(), a.set(t, u));
    u.has(l) || (tc = !0, u.add(l), e = Xh.bind(null, e, t, l), t.then(e, e));
  }
  function Xh(e, t, l) {
    var a = e.pingCache;
    a !== null && a.delete(t), e.pingedLanes |= e.suspendedLanes & l, e.warmLanes &= ~l, Xe === e && (Me & l) === l && (Je === 4 || Je === 3 && (Me & 62914560) === Me && 300 > mt() - Ju ? (De & 2) === 0 && Ja(e, 0) : lc |= l, Va === Me && (Va = 0)), Wt(e);
  }
  function td(e, t) {
    t === 0 && (t = Jc()), e = ta(e, t), e !== null && (cn(e, t), Wt(e));
  }
  function Lh(e) {
    var t = e.memoizedState, l = 0;
    t !== null && (l = t.retryLane), td(e, l);
  }
  function Qh(e, t) {
    var l = 0;
    switch (e.tag) {
      case 31:
      case 13:
        var a = e.stateNode, u = e.memoizedState;
        u !== null && (l = u.retryLane);
        break;
      case 19:
        a = e.stateNode;
        break;
      case 22:
        a = e.stateNode._retryCache;
        break;
      default:
        throw Error(f(314));
    }
    a !== null && a.delete(t), td(e, l);
  }
  function Zh(e, t) {
    return Jl(e, t);
  }
  var ei = null, $a = null, fc = !1, ti = !1, oc = !1, Bl = 0;
  function Wt(e) {
    e !== $a && e.next === null && ($a === null ? ei = $a = e : $a = $a.next = e), ti = !0, fc || (fc = !0, Vh());
  }
  function Qn(e, t) {
    if (!oc && ti) {
      oc = !0;
      do
        for (var l = !1, a = ei; a !== null; ) {
          if (e !== 0) {
            var u = a.pendingLanes;
            if (u === 0) var i = 0;
            else {
              var c = a.suspendedLanes, r = a.pingedLanes;
              i = (1 << 31 - Nt(42 | e) + 1) - 1, i &= u & ~(c & ~r), i = i & 201326741 ? i & 201326741 | 1 : i ? i | 2 : 0;
            }
            i !== 0 && (l = !0, ud(a, i));
          } else
            i = Me, i = uu(
              a,
              a === Xe ? i : 0,
              a.cancelPendingCommit !== null || a.timeoutHandle !== -1
            ), (i & 3) === 0 || sn(a, i) || (l = !0, ud(a, i));
          a = a.next;
        }
      while (l);
      oc = !1;
    }
  }
  function wh() {
    ld();
  }
  function ld() {
    ti = fc = !1;
    var e = 0;
    Bl !== 0 && t0() && (e = Bl);
    for (var t = mt(), l = null, a = ei; a !== null; ) {
      var u = a.next, i = ad(a, t);
      i === 0 ? (a.next = null, l === null ? ei = u : l.next = u, u === null && ($a = l)) : (l = a, (e !== 0 || (i & 3) !== 0) && (ti = !0)), a = u;
    }
    et !== 0 && et !== 5 || Qn(e), Bl !== 0 && (Bl = 0);
  }
  function ad(e, t) {
    for (var l = e.suspendedLanes, a = e.pingedLanes, u = e.expirationTimes, i = e.pendingLanes & -62914561; 0 < i; ) {
      var c = 31 - Nt(i), r = 1 << c, v = u[c];
      v === -1 ? ((r & l) === 0 || (r & a) !== 0) && (u[c] = bm(r, t)) : v <= t && (e.expiredLanes |= r), i &= ~r;
    }
    if (t = Xe, l = Me, l = uu(
      e,
      e === t ? l : 0,
      e.cancelPendingCommit !== null || e.timeoutHandle !== -1
    ), a = e.callbackNode, l === 0 || e === t && (Ue === 2 || Ue === 9) || e.cancelPendingCommit !== null)
      return a !== null && a !== null && kl(a), e.callbackNode = null, e.callbackPriority = 0;
    if ((l & 3) === 0 || sn(e, l)) {
      if (t = l & -l, t === e.callbackPriority) return t;
      switch (a !== null && kl(a), Mi(l)) {
        case 2:
        case 8:
          l = tt;
          break;
        case 32:
          l = Pt;
          break;
        case 268435456:
          l = va;
          break;
        default:
          l = Pt;
      }
      return a = nd.bind(null, e), l = Jl(l, a), e.callbackPriority = t, e.callbackNode = l, t;
    }
    return a !== null && a !== null && kl(a), e.callbackPriority = 2, e.callbackNode = null, 2;
  }
  function nd(e, t) {
    if (et !== 0 && et !== 5)
      return e.callbackNode = null, e.callbackPriority = 0, null;
    var l = e.callbackNode;
    if (Pu() && e.callbackNode !== l)
      return null;
    var a = Me;
    return a = uu(
      e,
      e === Xe ? a : 0,
      e.cancelPendingCommit !== null || e.timeoutHandle !== -1
    ), a === 0 ? null : (Go(e, a, t), ad(e, mt()), e.callbackNode != null && e.callbackNode === l ? nd.bind(null, e) : null);
  }
  function ud(e, t) {
    if (Pu()) return null;
    Go(e, t, !0);
  }
  function Vh() {
    a0(function() {
      (De & 6) !== 0 ? Jl(
        Se,
        wh
      ) : ld();
    });
  }
  function dc() {
    if (Bl === 0) {
      var e = Ua;
      e === 0 && (e = lu, lu <<= 1, (lu & 261888) === 0 && (lu = 256)), Bl = e;
    }
    return Bl;
  }
  function id(e) {
    return e == null || typeof e == "symbol" || typeof e == "boolean" ? null : typeof e == "function" ? e : ru("" + e);
  }
  function sd(e, t) {
    var l = t.ownerDocument.createElement("input");
    return l.name = t.name, l.value = t.value, e.id && l.setAttribute("form", e.id), t.parentNode.insertBefore(l, t), e = new FormData(e), l.parentNode.removeChild(l), e;
  }
  function Kh(e, t, l, a, u) {
    if (t === "submit" && l && l.stateNode === u) {
      var i = id(
        (u[pt] || null).action
      ), c = a.submitter;
      c && (t = (t = c[pt] || null) ? id(t.formAction) : c.getAttribute("formAction"), t !== null && (i = t, c = null));
      var r = new mu(
        "action",
        "action",
        null,
        a,
        u
      );
      e.push({
        event: r,
        listeners: [
          {
            instance: null,
            listener: function() {
              if (a.defaultPrevented) {
                if (Bl !== 0) {
                  var v = c ? sd(u, c) : new FormData(u);
                  Ds(
                    l,
                    {
                      pending: !0,
                      data: v,
                      method: u.method,
                      action: i
                    },
                    null,
                    v
                  );
                }
              } else
                typeof i == "function" && (r.preventDefault(), v = c ? sd(u, c) : new FormData(u), Ds(
                  l,
                  {
                    pending: !0,
                    data: v,
                    method: u.method,
                    action: i
                  },
                  i,
                  v
                ));
            },
            currentTarget: u
          }
        ]
      });
    }
  }
  for (var mc = 0; mc < $i.length; mc++) {
    var hc = $i[mc], Jh = hc.toLowerCase(), kh = hc[0].toUpperCase() + hc.slice(1);
    wt(
      Jh,
      "on" + kh
    );
  }
  wt(qr, "onAnimationEnd"), wt(Br, "onAnimationIteration"), wt(Yr, "onAnimationStart"), wt("dblclick", "onDoubleClick"), wt("focusin", "onFocus"), wt("focusout", "onBlur"), wt(fh, "onTransitionRun"), wt(oh, "onTransitionStart"), wt(dh, "onTransitionCancel"), wt(Gr, "onTransitionEnd"), ga("onMouseEnter", ["mouseout", "mouseover"]), ga("onMouseLeave", ["mouseout", "mouseover"]), ga("onPointerEnter", ["pointerout", "pointerover"]), ga("onPointerLeave", ["pointerout", "pointerover"]), Wl(
    "onChange",
    "change click focusin focusout input keydown keyup selectionchange".split(" ")
  ), Wl(
    "onSelect",
    "focusout contextmenu dragend focusin keydown keyup mousedown mouseup selectionchange".split(
      " "
    )
  ), Wl("onBeforeInput", [
    "compositionend",
    "keypress",
    "textInput",
    "paste"
  ]), Wl(
    "onCompositionEnd",
    "compositionend focusout keydown keypress keyup mousedown".split(" ")
  ), Wl(
    "onCompositionStart",
    "compositionstart focusout keydown keypress keyup mousedown".split(" ")
  ), Wl(
    "onCompositionUpdate",
    "compositionupdate focusout keydown keypress keyup mousedown".split(" ")
  );
  var Zn = "abort canplay canplaythrough durationchange emptied encrypted ended error loadeddata loadedmetadata loadstart pause play playing progress ratechange resize seeked seeking stalled suspend timeupdate volumechange waiting".split(
    " "
  ), $h = new Set(
    "beforetoggle cancel close invalid load scroll scrollend toggle".split(" ").concat(Zn)
  );
  function cd(e, t) {
    t = (t & 4) !== 0;
    for (var l = 0; l < e.length; l++) {
      var a = e[l], u = a.event;
      a = a.listeners;
      e: {
        var i = void 0;
        if (t)
          for (var c = a.length - 1; 0 <= c; c--) {
            var r = a[c], v = r.instance, C = r.currentTarget;
            if (r = r.listener, v !== i && u.isPropagationStopped())
              break e;
            i = r, u.currentTarget = C;
            try {
              i(u);
            } catch (Q) {
              pu(Q);
            }
            u.currentTarget = null, i = v;
          }
        else
          for (c = 0; c < a.length; c++) {
            if (r = a[c], v = r.instance, C = r.currentTarget, r = r.listener, v !== i && u.isPropagationStopped())
              break e;
            i = r, u.currentTarget = C;
            try {
              i(u);
            } catch (Q) {
              pu(Q);
            }
            u.currentTarget = null, i = v;
          }
      }
    }
  }
  function Ee(e, t) {
    var l = t[zi];
    l === void 0 && (l = t[zi] = /* @__PURE__ */ new Set());
    var a = e + "__bubble";
    l.has(a) || (rd(t, e, 2, !1), l.add(a));
  }
  function vc(e, t, l) {
    var a = 0;
    t && (a |= 4), rd(
      l,
      e,
      a,
      t
    );
  }
  var li = "_reactListening" + Math.random().toString(36).slice(2);
  function pc(e) {
    if (!e[li]) {
      e[li] = !0, er.forEach(function(l) {
        l !== "selectionchange" && ($h.has(l) || vc(l, !1, e), vc(l, !0, e));
      });
      var t = e.nodeType === 9 ? e : e.ownerDocument;
      t === null || t[li] || (t[li] = !0, vc("selectionchange", !1, t));
    }
  }
  function rd(e, t, l, a) {
    switch (Yd(t)) {
      case 2:
        var u = S0;
        break;
      case 8:
        u = N0;
        break;
      default:
        u = Oc;
    }
    l = u.bind(
      null,
      t,
      l,
      e
    ), u = void 0, !qi || t !== "touchstart" && t !== "touchmove" && t !== "wheel" || (u = !0), a ? u !== void 0 ? e.addEventListener(t, l, {
      capture: !0,
      passive: u
    }) : e.addEventListener(t, l, !0) : u !== void 0 ? e.addEventListener(t, l, {
      passive: u
    }) : e.addEventListener(t, l, !1);
  }
  function bc(e, t, l, a, u) {
    var i = a;
    if ((t & 1) === 0 && (t & 2) === 0 && a !== null)
      e: for (; ; ) {
        if (a === null) return;
        var c = a.tag;
        if (c === 3 || c === 4) {
          var r = a.stateNode.containerInfo;
          if (r === u) break;
          if (c === 4)
            for (c = a.return; c !== null; ) {
              var v = c.tag;
              if ((v === 3 || v === 4) && c.stateNode.containerInfo === u)
                return;
              c = c.return;
            }
          for (; r !== null; ) {
            if (c = ba(r), c === null) return;
            if (v = c.tag, v === 5 || v === 6 || v === 26 || v === 27) {
              a = i = c;
              continue e;
            }
            r = r.parentNode;
          }
        }
        a = a.return;
      }
    dr(function() {
      var C = i, Q = Ui(l), $ = [];
      e: {
        var R = Xr.get(e);
        if (R !== void 0) {
          var B = mu, se = e;
          switch (e) {
            case "keypress":
              if (ou(l) === 0) break e;
            case "keydown":
            case "keyup":
              B = Qm;
              break;
            case "focusin":
              se = "focus", B = Xi;
              break;
            case "focusout":
              se = "blur", B = Xi;
              break;
            case "beforeblur":
            case "afterblur":
              B = Xi;
              break;
            case "click":
              if (l.button === 2) break e;
            case "auxclick":
            case "dblclick":
            case "mousedown":
            case "mousemove":
            case "mouseup":
            case "mouseout":
            case "mouseover":
            case "contextmenu":
              B = vr;
              break;
            case "drag":
            case "dragend":
            case "dragenter":
            case "dragexit":
            case "dragleave":
            case "dragover":
            case "dragstart":
            case "drop":
              B = Cm;
              break;
            case "touchcancel":
            case "touchend":
            case "touchmove":
            case "touchstart":
              B = Vm;
              break;
            case qr:
            case Br:
            case Yr:
              B = Rm;
              break;
            case Gr:
              B = Jm;
              break;
            case "scroll":
            case "scrollend":
              B = Tm;
              break;
            case "wheel":
              B = $m;
              break;
            case "copy":
            case "cut":
            case "paste":
              B = Hm;
              break;
            case "gotpointercapture":
            case "lostpointercapture":
            case "pointercancel":
            case "pointerdown":
            case "pointermove":
            case "pointerout":
            case "pointerover":
            case "pointerup":
              B = br;
              break;
            case "toggle":
            case "beforetoggle":
              B = Wm;
          }
          var he = (t & 4) !== 0, Ge = !he && (e === "scroll" || e === "scrollend"), N = he ? R !== null ? R + "Capture" : null : R;
          he = [];
          for (var g = C, A; g !== null; ) {
            var K = g;
            if (A = K.stateNode, K = K.tag, K !== 5 && K !== 26 && K !== 27 || A === null || N === null || (K = on(g, N), K != null && he.push(
              wn(g, K, A)
            )), Ge) break;
            g = g.return;
          }
          0 < he.length && (R = new B(
            R,
            se,
            null,
            l,
            Q
          ), $.push({ event: R, listeners: he }));
        }
      }
      if ((t & 7) === 0) {
        e: {
          if (R = e === "mouseover" || e === "pointerover", B = e === "mouseout" || e === "pointerout", R && l !== Ri && (se = l.relatedTarget || l.fromElement) && (ba(se) || se[pa]))
            break e;
          if ((B || R) && (R = Q.window === Q ? Q : (R = Q.ownerDocument) ? R.defaultView || R.parentWindow : window, B ? (se = l.relatedTarget || l.toElement, B = C, se = se ? ba(se) : null, se !== null && (Ge = z(se), he = se.tag, se !== Ge || he !== 5 && he !== 27 && he !== 6) && (se = null)) : (B = null, se = C), B !== se)) {
            if (he = vr, K = "onMouseLeave", N = "onMouseEnter", g = "mouse", (e === "pointerout" || e === "pointerover") && (he = br, K = "onPointerLeave", N = "onPointerEnter", g = "pointer"), Ge = B == null ? R : fn(B), A = se == null ? R : fn(se), R = new he(
              K,
              g + "leave",
              B,
              l,
              Q
            ), R.target = Ge, R.relatedTarget = A, K = null, ba(Q) === C && (he = new he(
              N,
              g + "enter",
              se,
              l,
              Q
            ), he.target = A, he.relatedTarget = Ge, K = he), Ge = K, B && se)
              t: {
                for (he = Fh, N = B, g = se, A = 0, K = N; K; K = he(K))
                  A++;
                K = 0;
                for (var oe = g; oe; oe = he(oe))
                  K++;
                for (; 0 < A - K; )
                  N = he(N), A--;
                for (; 0 < K - A; )
                  g = he(g), K--;
                for (; A--; ) {
                  if (N === g || g !== null && N === g.alternate) {
                    he = N;
                    break t;
                  }
                  N = he(N), g = he(g);
                }
                he = null;
              }
            else he = null;
            B !== null && fd(
              $,
              R,
              B,
              he,
              !1
            ), se !== null && Ge !== null && fd(
              $,
              Ge,
              se,
              he,
              !0
            );
          }
        }
        e: {
          if (R = C ? fn(C) : window, B = R.nodeName && R.nodeName.toLowerCase(), B === "select" || B === "input" && R.type === "file")
            var Ae = Er;
          else if (Sr(R))
            if (Mr)
              Ae = sh;
            else {
              Ae = uh;
              var re = nh;
            }
          else
            B = R.nodeName, !B || B.toLowerCase() !== "input" || R.type !== "checkbox" && R.type !== "radio" ? C && Di(C.elementType) && (Ae = Er) : Ae = ih;
          if (Ae && (Ae = Ae(e, C))) {
            Nr(
              $,
              Ae,
              l,
              Q
            );
            break e;
          }
          re && re(e, R, C), e === "focusout" && C && R.type === "number" && C.memoizedProps.value != null && Oi(R, "number", R.value);
        }
        switch (re = C ? fn(C) : window, e) {
          case "focusin":
            (Sr(re) || re.contentEditable === "true") && (Ma = re, Ki = C, xn = null);
            break;
          case "focusout":
            xn = Ki = Ma = null;
            break;
          case "mousedown":
            Ji = !0;
            break;
          case "contextmenu":
          case "mouseup":
          case "dragend":
            Ji = !1, Ur($, l, Q);
            break;
          case "selectionchange":
            if (rh) break;
          case "keydown":
          case "keyup":
            Ur($, l, Q);
        }
        var je;
        if (Qi)
          e: {
            switch (e) {
              case "compositionstart":
                var ze = "onCompositionStart";
                break e;
              case "compositionend":
                ze = "onCompositionEnd";
                break e;
              case "compositionupdate":
                ze = "onCompositionUpdate";
                break e;
            }
            ze = void 0;
          }
        else
          Ea ? jr(e, l) && (ze = "onCompositionEnd") : e === "keydown" && l.keyCode === 229 && (ze = "onCompositionStart");
        ze && (yr && l.locale !== "ko" && (Ea || ze !== "onCompositionStart" ? ze === "onCompositionEnd" && Ea && (je = mr()) : (jl = Q, Bi = "value" in jl ? jl.value : jl.textContent, Ea = !0)), re = ai(C, ze), 0 < re.length && (ze = new pr(
          ze,
          e,
          null,
          l,
          Q
        ), $.push({ event: ze, listeners: re }), je ? ze.data = je : (je = _r(l), je !== null && (ze.data = je)))), (je = Pm ? eh(e, l) : th(e, l)) && (ze = ai(C, "onBeforeInput"), 0 < ze.length && (re = new pr(
          "onBeforeInput",
          "beforeinput",
          null,
          l,
          Q
        ), $.push({
          event: re,
          listeners: ze
        }), re.data = je)), Kh(
          $,
          e,
          C,
          l,
          Q
        );
      }
      cd($, t);
    });
  }
  function wn(e, t, l) {
    return {
      instance: e,
      listener: t,
      currentTarget: l
    };
  }
  function ai(e, t) {
    for (var l = t + "Capture", a = []; e !== null; ) {
      var u = e, i = u.stateNode;
      if (u = u.tag, u !== 5 && u !== 26 && u !== 27 || i === null || (u = on(e, l), u != null && a.unshift(
        wn(e, u, i)
      ), u = on(e, t), u != null && a.push(
        wn(e, u, i)
      )), e.tag === 3) return a;
      e = e.return;
    }
    return [];
  }
  function Fh(e) {
    if (e === null) return null;
    do
      e = e.return;
    while (e && e.tag !== 5 && e.tag !== 27);
    return e || null;
  }
  function fd(e, t, l, a, u) {
    for (var i = t._reactName, c = []; l !== null && l !== a; ) {
      var r = l, v = r.alternate, C = r.stateNode;
      if (r = r.tag, v !== null && v === a) break;
      r !== 5 && r !== 26 && r !== 27 || C === null || (v = C, u ? (C = on(l, i), C != null && c.unshift(
        wn(l, C, v)
      )) : u || (C = on(l, i), C != null && c.push(
        wn(l, C, v)
      ))), l = l.return;
    }
    c.length !== 0 && e.push({ event: t, listeners: c });
  }
  var Wh = /\r\n?/g, Ih = /\u0000|\uFFFD/g;
  function od(e) {
    return (typeof e == "string" ? e : "" + e).replace(Wh, `
`).replace(Ih, "");
  }
  function dd(e, t) {
    return t = od(t), od(e) === t;
  }
  function Ye(e, t, l, a, u, i) {
    switch (l) {
      case "children":
        typeof a == "string" ? t === "body" || t === "textarea" && a === "" || _a(e, a) : (typeof a == "number" || typeof a == "bigint") && t !== "body" && _a(e, "" + a);
        break;
      case "className":
        su(e, "class", a);
        break;
      case "tabIndex":
        su(e, "tabindex", a);
        break;
      case "dir":
      case "role":
      case "viewBox":
      case "width":
      case "height":
        su(e, l, a);
        break;
      case "style":
        fr(e, a, i);
        break;
      case "data":
        if (t !== "object") {
          su(e, "data", a);
          break;
        }
      case "src":
      case "href":
        if (a === "" && (t !== "a" || l !== "href")) {
          e.removeAttribute(l);
          break;
        }
        if (a == null || typeof a == "function" || typeof a == "symbol" || typeof a == "boolean") {
          e.removeAttribute(l);
          break;
        }
        a = ru("" + a), e.setAttribute(l, a);
        break;
      case "action":
      case "formAction":
        if (typeof a == "function") {
          e.setAttribute(
            l,
            "javascript:throw new Error('A React form was unexpectedly submitted. If you called form.submit() manually, consider using form.requestSubmit() instead. If you\\'re trying to use event.stopPropagation() in a submit event handler, consider also calling event.preventDefault().')"
          );
          break;
        } else
          typeof i == "function" && (l === "formAction" ? (t !== "input" && Ye(e, t, "name", u.name, u, null), Ye(
            e,
            t,
            "formEncType",
            u.formEncType,
            u,
            null
          ), Ye(
            e,
            t,
            "formMethod",
            u.formMethod,
            u,
            null
          ), Ye(
            e,
            t,
            "formTarget",
            u.formTarget,
            u,
            null
          )) : (Ye(e, t, "encType", u.encType, u, null), Ye(e, t, "method", u.method, u, null), Ye(e, t, "target", u.target, u, null)));
        if (a == null || typeof a == "symbol" || typeof a == "boolean") {
          e.removeAttribute(l);
          break;
        }
        a = ru("" + a), e.setAttribute(l, a);
        break;
      case "onClick":
        a != null && (e.onclick = tl);
        break;
      case "onScroll":
        a != null && Ee("scroll", e);
        break;
      case "onScrollEnd":
        a != null && Ee("scrollend", e);
        break;
      case "dangerouslySetInnerHTML":
        if (a != null) {
          if (typeof a != "object" || !("__html" in a))
            throw Error(f(61));
          if (l = a.__html, l != null) {
            if (u.children != null) throw Error(f(60));
            e.innerHTML = l;
          }
        }
        break;
      case "multiple":
        e.multiple = a && typeof a != "function" && typeof a != "symbol";
        break;
      case "muted":
        e.muted = a && typeof a != "function" && typeof a != "symbol";
        break;
      case "suppressContentEditableWarning":
      case "suppressHydrationWarning":
      case "defaultValue":
      case "defaultChecked":
      case "innerHTML":
      case "ref":
        break;
      case "autoFocus":
        break;
      case "xlinkHref":
        if (a == null || typeof a == "function" || typeof a == "boolean" || typeof a == "symbol") {
          e.removeAttribute("xlink:href");
          break;
        }
        l = ru("" + a), e.setAttributeNS(
          "http://www.w3.org/1999/xlink",
          "xlink:href",
          l
        );
        break;
      case "contentEditable":
      case "spellCheck":
      case "draggable":
      case "value":
      case "autoReverse":
      case "externalResourcesRequired":
      case "focusable":
      case "preserveAlpha":
        a != null && typeof a != "function" && typeof a != "symbol" ? e.setAttribute(l, "" + a) : e.removeAttribute(l);
        break;
      case "inert":
      case "allowFullScreen":
      case "async":
      case "autoPlay":
      case "controls":
      case "default":
      case "defer":
      case "disabled":
      case "disablePictureInPicture":
      case "disableRemotePlayback":
      case "formNoValidate":
      case "hidden":
      case "loop":
      case "noModule":
      case "noValidate":
      case "open":
      case "playsInline":
      case "readOnly":
      case "required":
      case "reversed":
      case "scoped":
      case "seamless":
      case "itemScope":
        a && typeof a != "function" && typeof a != "symbol" ? e.setAttribute(l, "") : e.removeAttribute(l);
        break;
      case "capture":
      case "download":
        a === !0 ? e.setAttribute(l, "") : a !== !1 && a != null && typeof a != "function" && typeof a != "symbol" ? e.setAttribute(l, a) : e.removeAttribute(l);
        break;
      case "cols":
      case "rows":
      case "size":
      case "span":
        a != null && typeof a != "function" && typeof a != "symbol" && !isNaN(a) && 1 <= a ? e.setAttribute(l, a) : e.removeAttribute(l);
        break;
      case "rowSpan":
      case "start":
        a == null || typeof a == "function" || typeof a == "symbol" || isNaN(a) ? e.removeAttribute(l) : e.setAttribute(l, a);
        break;
      case "popover":
        Ee("beforetoggle", e), Ee("toggle", e), iu(e, "popover", a);
        break;
      case "xlinkActuate":
        el(
          e,
          "http://www.w3.org/1999/xlink",
          "xlink:actuate",
          a
        );
        break;
      case "xlinkArcrole":
        el(
          e,
          "http://www.w3.org/1999/xlink",
          "xlink:arcrole",
          a
        );
        break;
      case "xlinkRole":
        el(
          e,
          "http://www.w3.org/1999/xlink",
          "xlink:role",
          a
        );
        break;
      case "xlinkShow":
        el(
          e,
          "http://www.w3.org/1999/xlink",
          "xlink:show",
          a
        );
        break;
      case "xlinkTitle":
        el(
          e,
          "http://www.w3.org/1999/xlink",
          "xlink:title",
          a
        );
        break;
      case "xlinkType":
        el(
          e,
          "http://www.w3.org/1999/xlink",
          "xlink:type",
          a
        );
        break;
      case "xmlBase":
        el(
          e,
          "http://www.w3.org/XML/1998/namespace",
          "xml:base",
          a
        );
        break;
      case "xmlLang":
        el(
          e,
          "http://www.w3.org/XML/1998/namespace",
          "xml:lang",
          a
        );
        break;
      case "xmlSpace":
        el(
          e,
          "http://www.w3.org/XML/1998/namespace",
          "xml:space",
          a
        );
        break;
      case "is":
        iu(e, "is", a);
        break;
      case "innerText":
      case "textContent":
        break;
      default:
        (!(2 < l.length) || l[0] !== "o" && l[0] !== "O" || l[1] !== "n" && l[1] !== "N") && (l = Mm.get(l) || l, iu(e, l, a));
    }
  }
  function yc(e, t, l, a, u, i) {
    switch (l) {
      case "style":
        fr(e, a, i);
        break;
      case "dangerouslySetInnerHTML":
        if (a != null) {
          if (typeof a != "object" || !("__html" in a))
            throw Error(f(61));
          if (l = a.__html, l != null) {
            if (u.children != null) throw Error(f(60));
            e.innerHTML = l;
          }
        }
        break;
      case "children":
        typeof a == "string" ? _a(e, a) : (typeof a == "number" || typeof a == "bigint") && _a(e, "" + a);
        break;
      case "onScroll":
        a != null && Ee("scroll", e);
        break;
      case "onScrollEnd":
        a != null && Ee("scrollend", e);
        break;
      case "onClick":
        a != null && (e.onclick = tl);
        break;
      case "suppressContentEditableWarning":
      case "suppressHydrationWarning":
      case "innerHTML":
      case "ref":
        break;
      case "innerText":
      case "textContent":
        break;
      default:
        if (!tr.hasOwnProperty(l))
          e: {
            if (l[0] === "o" && l[1] === "n" && (u = l.endsWith("Capture"), t = l.slice(2, u ? l.length - 7 : void 0), i = e[pt] || null, i = i != null ? i[l] : null, typeof i == "function" && e.removeEventListener(t, i, u), typeof a == "function")) {
              typeof i != "function" && i !== null && (l in e ? e[l] = null : e.hasAttribute(l) && e.removeAttribute(l)), e.addEventListener(t, a, u);
              break e;
            }
            l in e ? e[l] = a : a === !0 ? e.setAttribute(l, "") : iu(e, l, a);
          }
    }
  }
  function rt(e, t, l) {
    switch (t) {
      case "div":
      case "span":
      case "svg":
      case "path":
      case "a":
      case "g":
      case "p":
      case "li":
        break;
      case "img":
        Ee("error", e), Ee("load", e);
        var a = !1, u = !1, i;
        for (i in l)
          if (l.hasOwnProperty(i)) {
            var c = l[i];
            if (c != null)
              switch (i) {
                case "src":
                  a = !0;
                  break;
                case "srcSet":
                  u = !0;
                  break;
                case "children":
                case "dangerouslySetInnerHTML":
                  throw Error(f(137, t));
                default:
                  Ye(e, t, i, c, l, null);
              }
          }
        u && Ye(e, t, "srcSet", l.srcSet, l, null), a && Ye(e, t, "src", l.src, l, null);
        return;
      case "input":
        Ee("invalid", e);
        var r = i = c = u = null, v = null, C = null;
        for (a in l)
          if (l.hasOwnProperty(a)) {
            var Q = l[a];
            if (Q != null)
              switch (a) {
                case "name":
                  u = Q;
                  break;
                case "type":
                  c = Q;
                  break;
                case "checked":
                  v = Q;
                  break;
                case "defaultChecked":
                  C = Q;
                  break;
                case "value":
                  i = Q;
                  break;
                case "defaultValue":
                  r = Q;
                  break;
                case "children":
                case "dangerouslySetInnerHTML":
                  if (Q != null)
                    throw Error(f(137, t));
                  break;
                default:
                  Ye(e, t, a, Q, l, null);
              }
          }
        ir(
          e,
          i,
          r,
          v,
          C,
          c,
          u,
          !1
        );
        return;
      case "select":
        Ee("invalid", e), a = c = i = null;
        for (u in l)
          if (l.hasOwnProperty(u) && (r = l[u], r != null))
            switch (u) {
              case "value":
                i = r;
                break;
              case "defaultValue":
                c = r;
                break;
              case "multiple":
                a = r;
              default:
                Ye(e, t, u, r, l, null);
            }
        t = i, l = c, e.multiple = !!a, t != null ? ja(e, !!a, t, !1) : l != null && ja(e, !!a, l, !0);
        return;
      case "textarea":
        Ee("invalid", e), i = u = a = null;
        for (c in l)
          if (l.hasOwnProperty(c) && (r = l[c], r != null))
            switch (c) {
              case "value":
                a = r;
                break;
              case "defaultValue":
                u = r;
                break;
              case "children":
                i = r;
                break;
              case "dangerouslySetInnerHTML":
                if (r != null) throw Error(f(91));
                break;
              default:
                Ye(e, t, c, r, l, null);
            }
        cr(e, a, u, i);
        return;
      case "option":
        for (v in l)
          if (l.hasOwnProperty(v) && (a = l[v], a != null))
            switch (v) {
              case "selected":
                e.selected = a && typeof a != "function" && typeof a != "symbol";
                break;
              default:
                Ye(e, t, v, a, l, null);
            }
        return;
      case "dialog":
        Ee("beforetoggle", e), Ee("toggle", e), Ee("cancel", e), Ee("close", e);
        break;
      case "iframe":
      case "object":
        Ee("load", e);
        break;
      case "video":
      case "audio":
        for (a = 0; a < Zn.length; a++)
          Ee(Zn[a], e);
        break;
      case "image":
        Ee("error", e), Ee("load", e);
        break;
      case "details":
        Ee("toggle", e);
        break;
      case "embed":
      case "source":
      case "link":
        Ee("error", e), Ee("load", e);
      case "area":
      case "base":
      case "br":
      case "col":
      case "hr":
      case "keygen":
      case "meta":
      case "param":
      case "track":
      case "wbr":
      case "menuitem":
        for (C in l)
          if (l.hasOwnProperty(C) && (a = l[C], a != null))
            switch (C) {
              case "children":
              case "dangerouslySetInnerHTML":
                throw Error(f(137, t));
              default:
                Ye(e, t, C, a, l, null);
            }
        return;
      default:
        if (Di(t)) {
          for (Q in l)
            l.hasOwnProperty(Q) && (a = l[Q], a !== void 0 && yc(
              e,
              t,
              Q,
              a,
              l,
              void 0
            ));
          return;
        }
    }
    for (r in l)
      l.hasOwnProperty(r) && (a = l[r], a != null && Ye(e, t, r, a, l, null));
  }
  function Ph(e, t, l, a) {
    switch (t) {
      case "div":
      case "span":
      case "svg":
      case "path":
      case "a":
      case "g":
      case "p":
      case "li":
        break;
      case "input":
        var u = null, i = null, c = null, r = null, v = null, C = null, Q = null;
        for (B in l) {
          var $ = l[B];
          if (l.hasOwnProperty(B) && $ != null)
            switch (B) {
              case "checked":
                break;
              case "value":
                break;
              case "defaultValue":
                v = $;
              default:
                a.hasOwnProperty(B) || Ye(e, t, B, null, a, $);
            }
        }
        for (var R in a) {
          var B = a[R];
          if ($ = l[R], a.hasOwnProperty(R) && (B != null || $ != null))
            switch (R) {
              case "type":
                i = B;
                break;
              case "name":
                u = B;
                break;
              case "checked":
                C = B;
                break;
              case "defaultChecked":
                Q = B;
                break;
              case "value":
                c = B;
                break;
              case "defaultValue":
                r = B;
                break;
              case "children":
              case "dangerouslySetInnerHTML":
                if (B != null)
                  throw Error(f(137, t));
                break;
              default:
                B !== $ && Ye(
                  e,
                  t,
                  R,
                  B,
                  a,
                  $
                );
            }
        }
        Ci(
          e,
          c,
          r,
          v,
          C,
          Q,
          i,
          u
        );
        return;
      case "select":
        B = c = r = R = null;
        for (i in l)
          if (v = l[i], l.hasOwnProperty(i) && v != null)
            switch (i) {
              case "value":
                break;
              case "multiple":
                B = v;
              default:
                a.hasOwnProperty(i) || Ye(
                  e,
                  t,
                  i,
                  null,
                  a,
                  v
                );
            }
        for (u in a)
          if (i = a[u], v = l[u], a.hasOwnProperty(u) && (i != null || v != null))
            switch (u) {
              case "value":
                R = i;
                break;
              case "defaultValue":
                r = i;
                break;
              case "multiple":
                c = i;
              default:
                i !== v && Ye(
                  e,
                  t,
                  u,
                  i,
                  a,
                  v
                );
            }
        t = r, l = c, a = B, R != null ? ja(e, !!l, R, !1) : !!a != !!l && (t != null ? ja(e, !!l, t, !0) : ja(e, !!l, l ? [] : "", !1));
        return;
      case "textarea":
        B = R = null;
        for (r in l)
          if (u = l[r], l.hasOwnProperty(r) && u != null && !a.hasOwnProperty(r))
            switch (r) {
              case "value":
                break;
              case "children":
                break;
              default:
                Ye(e, t, r, null, a, u);
            }
        for (c in a)
          if (u = a[c], i = l[c], a.hasOwnProperty(c) && (u != null || i != null))
            switch (c) {
              case "value":
                R = u;
                break;
              case "defaultValue":
                B = u;
                break;
              case "children":
                break;
              case "dangerouslySetInnerHTML":
                if (u != null) throw Error(f(91));
                break;
              default:
                u !== i && Ye(e, t, c, u, a, i);
            }
        sr(e, R, B);
        return;
      case "option":
        for (var se in l)
          if (R = l[se], l.hasOwnProperty(se) && R != null && !a.hasOwnProperty(se))
            switch (se) {
              case "selected":
                e.selected = !1;
                break;
              default:
                Ye(
                  e,
                  t,
                  se,
                  null,
                  a,
                  R
                );
            }
        for (v in a)
          if (R = a[v], B = l[v], a.hasOwnProperty(v) && R !== B && (R != null || B != null))
            switch (v) {
              case "selected":
                e.selected = R && typeof R != "function" && typeof R != "symbol";
                break;
              default:
                Ye(
                  e,
                  t,
                  v,
                  R,
                  a,
                  B
                );
            }
        return;
      case "img":
      case "link":
      case "area":
      case "base":
      case "br":
      case "col":
      case "embed":
      case "hr":
      case "keygen":
      case "meta":
      case "param":
      case "source":
      case "track":
      case "wbr":
      case "menuitem":
        for (var he in l)
          R = l[he], l.hasOwnProperty(he) && R != null && !a.hasOwnProperty(he) && Ye(e, t, he, null, a, R);
        for (C in a)
          if (R = a[C], B = l[C], a.hasOwnProperty(C) && R !== B && (R != null || B != null))
            switch (C) {
              case "children":
              case "dangerouslySetInnerHTML":
                if (R != null)
                  throw Error(f(137, t));
                break;
              default:
                Ye(
                  e,
                  t,
                  C,
                  R,
                  a,
                  B
                );
            }
        return;
      default:
        if (Di(t)) {
          for (var Ge in l)
            R = l[Ge], l.hasOwnProperty(Ge) && R !== void 0 && !a.hasOwnProperty(Ge) && yc(
              e,
              t,
              Ge,
              void 0,
              a,
              R
            );
          for (Q in a)
            R = a[Q], B = l[Q], !a.hasOwnProperty(Q) || R === B || R === void 0 && B === void 0 || yc(
              e,
              t,
              Q,
              R,
              a,
              B
            );
          return;
        }
    }
    for (var N in l)
      R = l[N], l.hasOwnProperty(N) && R != null && !a.hasOwnProperty(N) && Ye(e, t, N, null, a, R);
    for ($ in a)
      R = a[$], B = l[$], !a.hasOwnProperty($) || R === B || R == null && B == null || Ye(e, t, $, R, a, B);
  }
  function md(e) {
    switch (e) {
      case "css":
      case "script":
      case "font":
      case "img":
      case "image":
      case "input":
      case "link":
        return !0;
      default:
        return !1;
    }
  }
  function e0() {
    if (typeof performance.getEntriesByType == "function") {
      for (var e = 0, t = 0, l = performance.getEntriesByType("resource"), a = 0; a < l.length; a++) {
        var u = l[a], i = u.transferSize, c = u.initiatorType, r = u.duration;
        if (i && r && md(c)) {
          for (c = 0, r = u.responseEnd, a += 1; a < l.length; a++) {
            var v = l[a], C = v.startTime;
            if (C > r) break;
            var Q = v.transferSize, $ = v.initiatorType;
            Q && md($) && (v = v.responseEnd, c += Q * (v < r ? 1 : (r - C) / (v - C)));
          }
          if (--a, t += 8 * (i + c) / (u.duration / 1e3), e++, 10 < e) break;
        }
      }
      if (0 < e) return t / e / 1e6;
    }
    return navigator.connection && (e = navigator.connection.downlink, typeof e == "number") ? e : 5;
  }
  var xc = null, gc = null;
  function ni(e) {
    return e.nodeType === 9 ? e : e.ownerDocument;
  }
  function hd(e) {
    switch (e) {
      case "http://www.w3.org/2000/svg":
        return 1;
      case "http://www.w3.org/1998/Math/MathML":
        return 2;
      default:
        return 0;
    }
  }
  function vd(e, t) {
    if (e === 0)
      switch (t) {
        case "svg":
          return 1;
        case "math":
          return 2;
        default:
          return 0;
      }
    return e === 1 && t === "foreignObject" ? 0 : e;
  }
  function jc(e, t) {
    return e === "textarea" || e === "noscript" || typeof t.children == "string" || typeof t.children == "number" || typeof t.children == "bigint" || typeof t.dangerouslySetInnerHTML == "object" && t.dangerouslySetInnerHTML !== null && t.dangerouslySetInnerHTML.__html != null;
  }
  var _c = null;
  function t0() {
    var e = window.event;
    return e && e.type === "popstate" ? e === _c ? !1 : (_c = e, !0) : (_c = null, !1);
  }
  var pd = typeof setTimeout == "function" ? setTimeout : void 0, l0 = typeof clearTimeout == "function" ? clearTimeout : void 0, bd = typeof Promise == "function" ? Promise : void 0, a0 = typeof queueMicrotask == "function" ? queueMicrotask : typeof bd < "u" ? function(e) {
    return bd.resolve(null).then(e).catch(n0);
  } : pd;
  function n0(e) {
    setTimeout(function() {
      throw e;
    });
  }
  function Yl(e) {
    return e === "head";
  }
  function yd(e, t) {
    var l = t, a = 0;
    do {
      var u = l.nextSibling;
      if (e.removeChild(l), u && u.nodeType === 8)
        if (l = u.data, l === "/$" || l === "/&") {
          if (a === 0) {
            e.removeChild(u), Pa(t);
            return;
          }
          a--;
        } else if (l === "$" || l === "$?" || l === "$~" || l === "$!" || l === "&")
          a++;
        else if (l === "html")
          Vn(e.ownerDocument.documentElement);
        else if (l === "head") {
          l = e.ownerDocument.head, Vn(l);
          for (var i = l.firstChild; i; ) {
            var c = i.nextSibling, r = i.nodeName;
            i[rn] || r === "SCRIPT" || r === "STYLE" || r === "LINK" && i.rel.toLowerCase() === "stylesheet" || l.removeChild(i), i = c;
          }
        } else
          l === "body" && Vn(e.ownerDocument.body);
      l = u;
    } while (l);
    Pa(t);
  }
  function xd(e, t) {
    var l = e;
    e = 0;
    do {
      var a = l.nextSibling;
      if (l.nodeType === 1 ? t ? (l._stashedDisplay = l.style.display, l.style.display = "none") : (l.style.display = l._stashedDisplay || "", l.getAttribute("style") === "" && l.removeAttribute("style")) : l.nodeType === 3 && (t ? (l._stashedText = l.nodeValue, l.nodeValue = "") : l.nodeValue = l._stashedText || ""), a && a.nodeType === 8)
        if (l = a.data, l === "/$") {
          if (e === 0) break;
          e--;
        } else
          l !== "$" && l !== "$?" && l !== "$~" && l !== "$!" || e++;
      l = a;
    } while (l);
  }
  function Sc(e) {
    var t = e.firstChild;
    for (t && t.nodeType === 10 && (t = t.nextSibling); t; ) {
      var l = t;
      switch (t = t.nextSibling, l.nodeName) {
        case "HTML":
        case "HEAD":
        case "BODY":
          Sc(l), Ti(l);
          continue;
        case "SCRIPT":
        case "STYLE":
          continue;
        case "LINK":
          if (l.rel.toLowerCase() === "stylesheet") continue;
      }
      e.removeChild(l);
    }
  }
  function u0(e, t, l, a) {
    for (; e.nodeType === 1; ) {
      var u = l;
      if (e.nodeName.toLowerCase() !== t.toLowerCase()) {
        if (!a && (e.nodeName !== "INPUT" || e.type !== "hidden"))
          break;
      } else if (a) {
        if (!e[rn])
          switch (t) {
            case "meta":
              if (!e.hasAttribute("itemprop")) break;
              return e;
            case "link":
              if (i = e.getAttribute("rel"), i === "stylesheet" && e.hasAttribute("data-precedence"))
                break;
              if (i !== u.rel || e.getAttribute("href") !== (u.href == null || u.href === "" ? null : u.href) || e.getAttribute("crossorigin") !== (u.crossOrigin == null ? null : u.crossOrigin) || e.getAttribute("title") !== (u.title == null ? null : u.title))
                break;
              return e;
            case "style":
              if (e.hasAttribute("data-precedence")) break;
              return e;
            case "script":
              if (i = e.getAttribute("src"), (i !== (u.src == null ? null : u.src) || e.getAttribute("type") !== (u.type == null ? null : u.type) || e.getAttribute("crossorigin") !== (u.crossOrigin == null ? null : u.crossOrigin)) && i && e.hasAttribute("async") && !e.hasAttribute("itemprop"))
                break;
              return e;
            default:
              return e;
          }
      } else if (t === "input" && e.type === "hidden") {
        var i = u.name == null ? null : "" + u.name;
        if (u.type === "hidden" && e.getAttribute("name") === i)
          return e;
      } else return e;
      if (e = Lt(e.nextSibling), e === null) break;
    }
    return null;
  }
  function i0(e, t, l) {
    if (t === "") return null;
    for (; e.nodeType !== 3; )
      if ((e.nodeType !== 1 || e.nodeName !== "INPUT" || e.type !== "hidden") && !l || (e = Lt(e.nextSibling), e === null)) return null;
    return e;
  }
  function gd(e, t) {
    for (; e.nodeType !== 8; )
      if ((e.nodeType !== 1 || e.nodeName !== "INPUT" || e.type !== "hidden") && !t || (e = Lt(e.nextSibling), e === null)) return null;
    return e;
  }
  function Nc(e) {
    return e.data === "$?" || e.data === "$~";
  }
  function Ec(e) {
    return e.data === "$!" || e.data === "$?" && e.ownerDocument.readyState !== "loading";
  }
  function s0(e, t) {
    var l = e.ownerDocument;
    if (e.data === "$~") e._reactRetry = t;
    else if (e.data !== "$?" || l.readyState !== "loading")
      t();
    else {
      var a = function() {
        t(), l.removeEventListener("DOMContentLoaded", a);
      };
      l.addEventListener("DOMContentLoaded", a), e._reactRetry = a;
    }
  }
  function Lt(e) {
    for (; e != null; e = e.nextSibling) {
      var t = e.nodeType;
      if (t === 1 || t === 3) break;
      if (t === 8) {
        if (t = e.data, t === "$" || t === "$!" || t === "$?" || t === "$~" || t === "&" || t === "F!" || t === "F")
          break;
        if (t === "/$" || t === "/&") return null;
      }
    }
    return e;
  }
  var Mc = null;
  function jd(e) {
    e = e.nextSibling;
    for (var t = 0; e; ) {
      if (e.nodeType === 8) {
        var l = e.data;
        if (l === "/$" || l === "/&") {
          if (t === 0)
            return Lt(e.nextSibling);
          t--;
        } else
          l !== "$" && l !== "$!" && l !== "$?" && l !== "$~" && l !== "&" || t++;
      }
      e = e.nextSibling;
    }
    return null;
  }
  function _d(e) {
    e = e.previousSibling;
    for (var t = 0; e; ) {
      if (e.nodeType === 8) {
        var l = e.data;
        if (l === "$" || l === "$!" || l === "$?" || l === "$~" || l === "&") {
          if (t === 0) return e;
          t--;
        } else l !== "/$" && l !== "/&" || t++;
      }
      e = e.previousSibling;
    }
    return null;
  }
  function Sd(e, t, l) {
    switch (t = ni(l), e) {
      case "html":
        if (e = t.documentElement, !e) throw Error(f(452));
        return e;
      case "head":
        if (e = t.head, !e) throw Error(f(453));
        return e;
      case "body":
        if (e = t.body, !e) throw Error(f(454));
        return e;
      default:
        throw Error(f(451));
    }
  }
  function Vn(e) {
    for (var t = e.attributes; t.length; )
      e.removeAttributeNode(t[0]);
    Ti(e);
  }
  var Qt = /* @__PURE__ */ new Map(), Nd = /* @__PURE__ */ new Set();
  function ui(e) {
    return typeof e.getRootNode == "function" ? e.getRootNode() : e.nodeType === 9 ? e : e.ownerDocument;
  }
  var bl = Z.d;
  Z.d = {
    f: c0,
    r: r0,
    D: f0,
    C: o0,
    L: d0,
    m: m0,
    X: v0,
    S: h0,
    M: p0
  };
  function c0() {
    var e = bl.f(), t = Fu();
    return e || t;
  }
  function r0(e) {
    var t = ya(e);
    t !== null && t.tag === 5 && t.type === "form" ? Xf(t) : bl.r(e);
  }
  var Fa = typeof document > "u" ? null : document;
  function Ed(e, t, l) {
    var a = Fa;
    if (a && typeof t == "string" && t) {
      var u = Ut(t);
      u = 'link[rel="' + e + '"][href="' + u + '"]', typeof l == "string" && (u += '[crossorigin="' + l + '"]'), Nd.has(u) || (Nd.add(u), e = { rel: e, crossOrigin: l, href: t }, a.querySelector(u) === null && (t = a.createElement("link"), rt(t, "link", e), lt(t), a.head.appendChild(t)));
    }
  }
  function f0(e) {
    bl.D(e), Ed("dns-prefetch", e, null);
  }
  function o0(e, t) {
    bl.C(e, t), Ed("preconnect", e, t);
  }
  function d0(e, t, l) {
    bl.L(e, t, l);
    var a = Fa;
    if (a && e && t) {
      var u = 'link[rel="preload"][as="' + Ut(t) + '"]';
      t === "image" && l && l.imageSrcSet ? (u += '[imagesrcset="' + Ut(
        l.imageSrcSet
      ) + '"]', typeof l.imageSizes == "string" && (u += '[imagesizes="' + Ut(
        l.imageSizes
      ) + '"]')) : u += '[href="' + Ut(e) + '"]';
      var i = u;
      switch (t) {
        case "style":
          i = Wa(e);
          break;
        case "script":
          i = Ia(e);
      }
      Qt.has(i) || (e = y(
        {
          rel: "preload",
          href: t === "image" && l && l.imageSrcSet ? void 0 : e,
          as: t
        },
        l
      ), Qt.set(i, e), a.querySelector(u) !== null || t === "style" && a.querySelector(Kn(i)) || t === "script" && a.querySelector(Jn(i)) || (t = a.createElement("link"), rt(t, "link", e), lt(t), a.head.appendChild(t)));
    }
  }
  function m0(e, t) {
    bl.m(e, t);
    var l = Fa;
    if (l && e) {
      var a = t && typeof t.as == "string" ? t.as : "script", u = 'link[rel="modulepreload"][as="' + Ut(a) + '"][href="' + Ut(e) + '"]', i = u;
      switch (a) {
        case "audioworklet":
        case "paintworklet":
        case "serviceworker":
        case "sharedworker":
        case "worker":
        case "script":
          i = Ia(e);
      }
      if (!Qt.has(i) && (e = y({ rel: "modulepreload", href: e }, t), Qt.set(i, e), l.querySelector(u) === null)) {
        switch (a) {
          case "audioworklet":
          case "paintworklet":
          case "serviceworker":
          case "sharedworker":
          case "worker":
          case "script":
            if (l.querySelector(Jn(i)))
              return;
        }
        a = l.createElement("link"), rt(a, "link", e), lt(a), l.head.appendChild(a);
      }
    }
  }
  function h0(e, t, l) {
    bl.S(e, t, l);
    var a = Fa;
    if (a && e) {
      var u = xa(a).hoistableStyles, i = Wa(e);
      t = t || "default";
      var c = u.get(i);
      if (!c) {
        var r = { loading: 0, preload: null };
        if (c = a.querySelector(
          Kn(i)
        ))
          r.loading = 5;
        else {
          e = y(
            { rel: "stylesheet", href: e, "data-precedence": t },
            l
          ), (l = Qt.get(i)) && zc(e, l);
          var v = c = a.createElement("link");
          lt(v), rt(v, "link", e), v._p = new Promise(function(C, Q) {
            v.onload = C, v.onerror = Q;
          }), v.addEventListener("load", function() {
            r.loading |= 1;
          }), v.addEventListener("error", function() {
            r.loading |= 2;
          }), r.loading |= 4, ii(c, t, a);
        }
        c = {
          type: "stylesheet",
          instance: c,
          count: 1,
          state: r
        }, u.set(i, c);
      }
    }
  }
  function v0(e, t) {
    bl.X(e, t);
    var l = Fa;
    if (l && e) {
      var a = xa(l).hoistableScripts, u = Ia(e), i = a.get(u);
      i || (i = l.querySelector(Jn(u)), i || (e = y({ src: e, async: !0 }, t), (t = Qt.get(u)) && Tc(e, t), i = l.createElement("script"), lt(i), rt(i, "link", e), l.head.appendChild(i)), i = {
        type: "script",
        instance: i,
        count: 1,
        state: null
      }, a.set(u, i));
    }
  }
  function p0(e, t) {
    bl.M(e, t);
    var l = Fa;
    if (l && e) {
      var a = xa(l).hoistableScripts, u = Ia(e), i = a.get(u);
      i || (i = l.querySelector(Jn(u)), i || (e = y({ src: e, async: !0, type: "module" }, t), (t = Qt.get(u)) && Tc(e, t), i = l.createElement("script"), lt(i), rt(i, "link", e), l.head.appendChild(i)), i = {
        type: "script",
        instance: i,
        count: 1,
        state: null
      }, a.set(u, i));
    }
  }
  function Md(e, t, l, a) {
    var u = (u = ce.current) ? ui(u) : null;
    if (!u) throw Error(f(446));
    switch (e) {
      case "meta":
      case "title":
        return null;
      case "style":
        return typeof l.precedence == "string" && typeof l.href == "string" ? (t = Wa(l.href), l = xa(
          u
        ).hoistableStyles, a = l.get(t), a || (a = {
          type: "style",
          instance: null,
          count: 0,
          state: null
        }, l.set(t, a)), a) : { type: "void", instance: null, count: 0, state: null };
      case "link":
        if (l.rel === "stylesheet" && typeof l.href == "string" && typeof l.precedence == "string") {
          e = Wa(l.href);
          var i = xa(
            u
          ).hoistableStyles, c = i.get(e);
          if (c || (u = u.ownerDocument || u, c = {
            type: "stylesheet",
            instance: null,
            count: 0,
            state: { loading: 0, preload: null }
          }, i.set(e, c), (i = u.querySelector(
            Kn(e)
          )) && !i._p && (c.instance = i, c.state.loading = 5), Qt.has(e) || (l = {
            rel: "preload",
            as: "style",
            href: l.href,
            crossOrigin: l.crossOrigin,
            integrity: l.integrity,
            media: l.media,
            hrefLang: l.hrefLang,
            referrerPolicy: l.referrerPolicy
          }, Qt.set(e, l), i || b0(
            u,
            e,
            l,
            c.state
          ))), t && a === null)
            throw Error(f(528, ""));
          return c;
        }
        if (t && a !== null)
          throw Error(f(529, ""));
        return null;
      case "script":
        return t = l.async, l = l.src, typeof l == "string" && t && typeof t != "function" && typeof t != "symbol" ? (t = Ia(l), l = xa(
          u
        ).hoistableScripts, a = l.get(t), a || (a = {
          type: "script",
          instance: null,
          count: 0,
          state: null
        }, l.set(t, a)), a) : { type: "void", instance: null, count: 0, state: null };
      default:
        throw Error(f(444, e));
    }
  }
  function Wa(e) {
    return 'href="' + Ut(e) + '"';
  }
  function Kn(e) {
    return 'link[rel="stylesheet"][' + e + "]";
  }
  function zd(e) {
    return y({}, e, {
      "data-precedence": e.precedence,
      precedence: null
    });
  }
  function b0(e, t, l, a) {
    e.querySelector('link[rel="preload"][as="style"][' + t + "]") ? a.loading = 1 : (t = e.createElement("link"), a.preload = t, t.addEventListener("load", function() {
      return a.loading |= 1;
    }), t.addEventListener("error", function() {
      return a.loading |= 2;
    }), rt(t, "link", l), lt(t), e.head.appendChild(t));
  }
  function Ia(e) {
    return '[src="' + Ut(e) + '"]';
  }
  function Jn(e) {
    return "script[async]" + e;
  }
  function Td(e, t, l) {
    if (t.count++, t.instance === null)
      switch (t.type) {
        case "style":
          var a = e.querySelector(
            'style[data-href~="' + Ut(l.href) + '"]'
          );
          if (a)
            return t.instance = a, lt(a), a;
          var u = y({}, l, {
            "data-href": l.href,
            "data-precedence": l.precedence,
            href: null,
            precedence: null
          });
          return a = (e.ownerDocument || e).createElement(
            "style"
          ), lt(a), rt(a, "style", u), ii(a, l.precedence, e), t.instance = a;
        case "stylesheet":
          u = Wa(l.href);
          var i = e.querySelector(
            Kn(u)
          );
          if (i)
            return t.state.loading |= 4, t.instance = i, lt(i), i;
          a = zd(l), (u = Qt.get(u)) && zc(a, u), i = (e.ownerDocument || e).createElement("link"), lt(i);
          var c = i;
          return c._p = new Promise(function(r, v) {
            c.onload = r, c.onerror = v;
          }), rt(i, "link", a), t.state.loading |= 4, ii(i, l.precedence, e), t.instance = i;
        case "script":
          return i = Ia(l.src), (u = e.querySelector(
            Jn(i)
          )) ? (t.instance = u, lt(u), u) : (a = l, (u = Qt.get(i)) && (a = y({}, l), Tc(a, u)), e = e.ownerDocument || e, u = e.createElement("script"), lt(u), rt(u, "link", a), e.head.appendChild(u), t.instance = u);
        case "void":
          return null;
        default:
          throw Error(f(443, t.type));
      }
    else
      t.type === "stylesheet" && (t.state.loading & 4) === 0 && (a = t.instance, t.state.loading |= 4, ii(a, l.precedence, e));
    return t.instance;
  }
  function ii(e, t, l) {
    for (var a = l.querySelectorAll(
      'link[rel="stylesheet"][data-precedence],style[data-precedence]'
    ), u = a.length ? a[a.length - 1] : null, i = u, c = 0; c < a.length; c++) {
      var r = a[c];
      if (r.dataset.precedence === t) i = r;
      else if (i !== u) break;
    }
    i ? i.parentNode.insertBefore(e, i.nextSibling) : (t = l.nodeType === 9 ? l.head : l, t.insertBefore(e, t.firstChild));
  }
  function zc(e, t) {
    e.crossOrigin == null && (e.crossOrigin = t.crossOrigin), e.referrerPolicy == null && (e.referrerPolicy = t.referrerPolicy), e.title == null && (e.title = t.title);
  }
  function Tc(e, t) {
    e.crossOrigin == null && (e.crossOrigin = t.crossOrigin), e.referrerPolicy == null && (e.referrerPolicy = t.referrerPolicy), e.integrity == null && (e.integrity = t.integrity);
  }
  var si = null;
  function Ad(e, t, l) {
    if (si === null) {
      var a = /* @__PURE__ */ new Map(), u = si = /* @__PURE__ */ new Map();
      u.set(l, a);
    } else
      u = si, a = u.get(l), a || (a = /* @__PURE__ */ new Map(), u.set(l, a));
    if (a.has(e)) return a;
    for (a.set(e, null), l = l.getElementsByTagName(e), u = 0; u < l.length; u++) {
      var i = l[u];
      if (!(i[rn] || i[ut] || e === "link" && i.getAttribute("rel") === "stylesheet") && i.namespaceURI !== "http://www.w3.org/2000/svg") {
        var c = i.getAttribute(t) || "";
        c = e + c;
        var r = a.get(c);
        r ? r.push(i) : a.set(c, [i]);
      }
    }
    return a;
  }
  function Cd(e, t, l) {
    e = e.ownerDocument || e, e.head.insertBefore(
      l,
      t === "title" ? e.querySelector("head > title") : null
    );
  }
  function y0(e, t, l) {
    if (l === 1 || t.itemProp != null) return !1;
    switch (e) {
      case "meta":
      case "title":
        return !0;
      case "style":
        if (typeof t.precedence != "string" || typeof t.href != "string" || t.href === "")
          break;
        return !0;
      case "link":
        if (typeof t.rel != "string" || typeof t.href != "string" || t.href === "" || t.onLoad || t.onError)
          break;
        switch (t.rel) {
          case "stylesheet":
            return e = t.disabled, typeof t.precedence == "string" && e == null;
          default:
            return !0;
        }
      case "script":
        if (t.async && typeof t.async != "function" && typeof t.async != "symbol" && !t.onLoad && !t.onError && t.src && typeof t.src == "string")
          return !0;
    }
    return !1;
  }
  function Od(e) {
    return !(e.type === "stylesheet" && (e.state.loading & 3) === 0);
  }
  function x0(e, t, l, a) {
    if (l.type === "stylesheet" && (typeof a.media != "string" || matchMedia(a.media).matches !== !1) && (l.state.loading & 4) === 0) {
      if (l.instance === null) {
        var u = Wa(a.href), i = t.querySelector(
          Kn(u)
        );
        if (i) {
          t = i._p, t !== null && typeof t == "object" && typeof t.then == "function" && (e.count++, e = ci.bind(e), t.then(e, e)), l.state.loading |= 4, l.instance = i, lt(i);
          return;
        }
        i = t.ownerDocument || t, a = zd(a), (u = Qt.get(u)) && zc(a, u), i = i.createElement("link"), lt(i);
        var c = i;
        c._p = new Promise(function(r, v) {
          c.onload = r, c.onerror = v;
        }), rt(i, "link", a), l.instance = i;
      }
      e.stylesheets === null && (e.stylesheets = /* @__PURE__ */ new Map()), e.stylesheets.set(l, t), (t = l.state.preload) && (l.state.loading & 3) === 0 && (e.count++, l = ci.bind(e), t.addEventListener("load", l), t.addEventListener("error", l));
    }
  }
  var Ac = 0;
  function g0(e, t) {
    return e.stylesheets && e.count === 0 && fi(e, e.stylesheets), 0 < e.count || 0 < e.imgCount ? function(l) {
      var a = setTimeout(function() {
        if (e.stylesheets && fi(e, e.stylesheets), e.unsuspend) {
          var i = e.unsuspend;
          e.unsuspend = null, i();
        }
      }, 6e4 + t);
      0 < e.imgBytes && Ac === 0 && (Ac = 62500 * e0());
      var u = setTimeout(
        function() {
          if (e.waitingForImages = !1, e.count === 0 && (e.stylesheets && fi(e, e.stylesheets), e.unsuspend)) {
            var i = e.unsuspend;
            e.unsuspend = null, i();
          }
        },
        (e.imgBytes > Ac ? 50 : 800) + t
      );
      return e.unsuspend = l, function() {
        e.unsuspend = null, clearTimeout(a), clearTimeout(u);
      };
    } : null;
  }
  function ci() {
    if (this.count--, this.count === 0 && (this.imgCount === 0 || !this.waitingForImages)) {
      if (this.stylesheets) fi(this, this.stylesheets);
      else if (this.unsuspend) {
        var e = this.unsuspend;
        this.unsuspend = null, e();
      }
    }
  }
  var ri = null;
  function fi(e, t) {
    e.stylesheets = null, e.unsuspend !== null && (e.count++, ri = /* @__PURE__ */ new Map(), t.forEach(j0, e), ri = null, ci.call(e));
  }
  function j0(e, t) {
    if (!(t.state.loading & 4)) {
      var l = ri.get(e);
      if (l) var a = l.get(null);
      else {
        l = /* @__PURE__ */ new Map(), ri.set(e, l);
        for (var u = e.querySelectorAll(
          "link[data-precedence],style[data-precedence]"
        ), i = 0; i < u.length; i++) {
          var c = u[i];
          (c.nodeName === "LINK" || c.getAttribute("media") !== "not all") && (l.set(c.dataset.precedence, c), a = c);
        }
        a && l.set(null, a);
      }
      u = t.instance, c = u.getAttribute("data-precedence"), i = l.get(c) || a, i === a && l.set(null, u), l.set(c, u), this.count++, a = ci.bind(this), u.addEventListener("load", a), u.addEventListener("error", a), i ? i.parentNode.insertBefore(u, i.nextSibling) : (e = e.nodeType === 9 ? e.head : e, e.insertBefore(u, e.firstChild)), t.state.loading |= 4;
    }
  }
  var kn = {
    $$typeof: V,
    Provider: null,
    Consumer: null,
    _currentValue: ne,
    _currentValue2: ne,
    _threadCount: 0
  };
  function _0(e, t, l, a, u, i, c, r, v) {
    this.tag = 1, this.containerInfo = e, this.pingCache = this.current = this.pendingChildren = null, this.timeoutHandle = -1, this.callbackNode = this.next = this.pendingContext = this.context = this.cancelPendingCommit = null, this.callbackPriority = 0, this.expirationTimes = Ni(-1), this.entangledLanes = this.shellSuspendCounter = this.errorRecoveryDisabledLanes = this.expiredLanes = this.warmLanes = this.pingedLanes = this.suspendedLanes = this.pendingLanes = 0, this.entanglements = Ni(0), this.hiddenUpdates = Ni(null), this.identifierPrefix = a, this.onUncaughtError = u, this.onCaughtError = i, this.onRecoverableError = c, this.pooledCache = null, this.pooledCacheLanes = 0, this.formState = v, this.incompleteTransitions = /* @__PURE__ */ new Map();
  }
  function Dd(e, t, l, a, u, i, c, r, v, C, Q, $) {
    return e = new _0(
      e,
      t,
      l,
      c,
      v,
      C,
      Q,
      $,
      r
    ), t = 1, i === !0 && (t |= 24), i = Mt(3, null, null, t), e.current = i, i.stateNode = e, t = cs(), t.refCount++, e.pooledCache = t, t.refCount++, i.memoizedState = {
      element: a,
      isDehydrated: l,
      cache: t
    }, ds(i), e;
  }
  function Rd(e) {
    return e ? (e = Aa, e) : Aa;
  }
  function Ud(e, t, l, a, u, i) {
    u = Rd(u), a.context === null ? a.context = u : a.pendingContext = u, a = zl(t), a.payload = { element: l }, i = i === void 0 ? null : i, i !== null && (a.callback = i), l = Tl(e, a, t), l !== null && (_t(l, e, t), Mn(l, e, t));
  }
  function Hd(e, t) {
    if (e = e.memoizedState, e !== null && e.dehydrated !== null) {
      var l = e.retryLane;
      e.retryLane = l !== 0 && l < t ? l : t;
    }
  }
  function Cc(e, t) {
    Hd(e, t), (e = e.alternate) && Hd(e, t);
  }
  function qd(e) {
    if (e.tag === 13 || e.tag === 31) {
      var t = ta(e, 67108864);
      t !== null && _t(t, e, 67108864), Cc(e, 67108864);
    }
  }
  function Bd(e) {
    if (e.tag === 13 || e.tag === 31) {
      var t = Ot();
      t = Ei(t);
      var l = ta(e, t);
      l !== null && _t(l, e, t), Cc(e, t);
    }
  }
  var oi = !0;
  function S0(e, t, l, a) {
    var u = S.T;
    S.T = null;
    var i = Z.p;
    try {
      Z.p = 2, Oc(e, t, l, a);
    } finally {
      Z.p = i, S.T = u;
    }
  }
  function N0(e, t, l, a) {
    var u = S.T;
    S.T = null;
    var i = Z.p;
    try {
      Z.p = 8, Oc(e, t, l, a);
    } finally {
      Z.p = i, S.T = u;
    }
  }
  function Oc(e, t, l, a) {
    if (oi) {
      var u = Dc(a);
      if (u === null)
        bc(
          e,
          t,
          a,
          di,
          l
        ), Gd(e, a);
      else if (M0(
        u,
        e,
        t,
        l,
        a
      ))
        a.stopPropagation();
      else if (Gd(e, a), t & 4 && -1 < E0.indexOf(e)) {
        for (; u !== null; ) {
          var i = ya(u);
          if (i !== null)
            switch (i.tag) {
              case 3:
                if (i = i.stateNode, i.current.memoizedState.isDehydrated) {
                  var c = Fl(i.pendingLanes);
                  if (c !== 0) {
                    var r = i;
                    for (r.pendingLanes |= 2, r.entangledLanes |= 2; c; ) {
                      var v = 1 << 31 - Nt(c);
                      r.entanglements[1] |= v, c &= ~v;
                    }
                    Wt(i), (De & 6) === 0 && (ku = mt() + 500, Qn(0));
                  }
                }
                break;
              case 31:
              case 13:
                r = ta(i, 2), r !== null && _t(r, i, 2), Fu(), Cc(i, 2);
            }
          if (i = Dc(a), i === null && bc(
            e,
            t,
            a,
            di,
            l
          ), i === u) break;
          u = i;
        }
        u !== null && a.stopPropagation();
      } else
        bc(
          e,
          t,
          a,
          null,
          l
        );
    }
  }
  function Dc(e) {
    return e = Ui(e), Rc(e);
  }
  var di = null;
  function Rc(e) {
    if (di = null, e = ba(e), e !== null) {
      var t = z(e);
      if (t === null) e = null;
      else {
        var l = t.tag;
        if (l === 13) {
          if (e = O(t), e !== null) return e;
          e = null;
        } else if (l === 31) {
          if (e = U(t), e !== null) return e;
          e = null;
        } else if (l === 3) {
          if (t.stateNode.current.memoizedState.isDehydrated)
            return t.tag === 3 ? t.stateNode.containerInfo : null;
          e = null;
        } else t !== e && (e = null);
      }
    }
    return di = e, null;
  }
  function Yd(e) {
    switch (e) {
      case "beforetoggle":
      case "cancel":
      case "click":
      case "close":
      case "contextmenu":
      case "copy":
      case "cut":
      case "auxclick":
      case "dblclick":
      case "dragend":
      case "dragstart":
      case "drop":
      case "focusin":
      case "focusout":
      case "input":
      case "invalid":
      case "keydown":
      case "keypress":
      case "keyup":
      case "mousedown":
      case "mouseup":
      case "paste":
      case "pause":
      case "play":
      case "pointercancel":
      case "pointerdown":
      case "pointerup":
      case "ratechange":
      case "reset":
      case "resize":
      case "seeked":
      case "submit":
      case "toggle":
      case "touchcancel":
      case "touchend":
      case "touchstart":
      case "volumechange":
      case "change":
      case "selectionchange":
      case "textInput":
      case "compositionstart":
      case "compositionend":
      case "compositionupdate":
      case "beforeblur":
      case "afterblur":
      case "beforeinput":
      case "blur":
      case "fullscreenchange":
      case "focus":
      case "hashchange":
      case "popstate":
      case "select":
      case "selectstart":
        return 2;
      case "drag":
      case "dragenter":
      case "dragexit":
      case "dragleave":
      case "dragover":
      case "mousemove":
      case "mouseout":
      case "mouseover":
      case "pointermove":
      case "pointerout":
      case "pointerover":
      case "scroll":
      case "touchmove":
      case "wheel":
      case "mouseenter":
      case "mouseleave":
      case "pointerenter":
      case "pointerleave":
        return 8;
      case "message":
        switch (k()) {
          case Se:
            return 2;
          case tt:
            return 8;
          case Pt:
          case $l:
            return 32;
          case va:
            return 268435456;
          default:
            return 32;
        }
      default:
        return 32;
    }
  }
  var Uc = !1, Gl = null, Xl = null, Ll = null, $n = /* @__PURE__ */ new Map(), Fn = /* @__PURE__ */ new Map(), Ql = [], E0 = "mousedown mouseup touchcancel touchend touchstart auxclick dblclick pointercancel pointerdown pointerup dragend dragstart drop compositionend compositionstart keydown keypress keyup input textInput copy cut paste click change contextmenu reset".split(
    " "
  );
  function Gd(e, t) {
    switch (e) {
      case "focusin":
      case "focusout":
        Gl = null;
        break;
      case "dragenter":
      case "dragleave":
        Xl = null;
        break;
      case "mouseover":
      case "mouseout":
        Ll = null;
        break;
      case "pointerover":
      case "pointerout":
        $n.delete(t.pointerId);
        break;
      case "gotpointercapture":
      case "lostpointercapture":
        Fn.delete(t.pointerId);
    }
  }
  function Wn(e, t, l, a, u, i) {
    return e === null || e.nativeEvent !== i ? (e = {
      blockedOn: t,
      domEventName: l,
      eventSystemFlags: a,
      nativeEvent: i,
      targetContainers: [u]
    }, t !== null && (t = ya(t), t !== null && qd(t)), e) : (e.eventSystemFlags |= a, t = e.targetContainers, u !== null && t.indexOf(u) === -1 && t.push(u), e);
  }
  function M0(e, t, l, a, u) {
    switch (t) {
      case "focusin":
        return Gl = Wn(
          Gl,
          e,
          t,
          l,
          a,
          u
        ), !0;
      case "dragenter":
        return Xl = Wn(
          Xl,
          e,
          t,
          l,
          a,
          u
        ), !0;
      case "mouseover":
        return Ll = Wn(
          Ll,
          e,
          t,
          l,
          a,
          u
        ), !0;
      case "pointerover":
        var i = u.pointerId;
        return $n.set(
          i,
          Wn(
            $n.get(i) || null,
            e,
            t,
            l,
            a,
            u
          )
        ), !0;
      case "gotpointercapture":
        return i = u.pointerId, Fn.set(
          i,
          Wn(
            Fn.get(i) || null,
            e,
            t,
            l,
            a,
            u
          )
        ), !0;
    }
    return !1;
  }
  function Xd(e) {
    var t = ba(e.target);
    if (t !== null) {
      var l = z(t);
      if (l !== null) {
        if (t = l.tag, t === 13) {
          if (t = O(l), t !== null) {
            e.blockedOn = t, Ic(e.priority, function() {
              Bd(l);
            });
            return;
          }
        } else if (t === 31) {
          if (t = U(l), t !== null) {
            e.blockedOn = t, Ic(e.priority, function() {
              Bd(l);
            });
            return;
          }
        } else if (t === 3 && l.stateNode.current.memoizedState.isDehydrated) {
          e.blockedOn = l.tag === 3 ? l.stateNode.containerInfo : null;
          return;
        }
      }
    }
    e.blockedOn = null;
  }
  function mi(e) {
    if (e.blockedOn !== null) return !1;
    for (var t = e.targetContainers; 0 < t.length; ) {
      var l = Dc(e.nativeEvent);
      if (l === null) {
        l = e.nativeEvent;
        var a = new l.constructor(
          l.type,
          l
        );
        Ri = a, l.target.dispatchEvent(a), Ri = null;
      } else
        return t = ya(l), t !== null && qd(t), e.blockedOn = l, !1;
      t.shift();
    }
    return !0;
  }
  function Ld(e, t, l) {
    mi(e) && l.delete(t);
  }
  function z0() {
    Uc = !1, Gl !== null && mi(Gl) && (Gl = null), Xl !== null && mi(Xl) && (Xl = null), Ll !== null && mi(Ll) && (Ll = null), $n.forEach(Ld), Fn.forEach(Ld);
  }
  function hi(e, t) {
    e.blockedOn === t && (e.blockedOn = null, Uc || (Uc = !0, s.unstable_scheduleCallback(
      s.unstable_NormalPriority,
      z0
    )));
  }
  var vi = null;
  function Qd(e) {
    vi !== e && (vi = e, s.unstable_scheduleCallback(
      s.unstable_NormalPriority,
      function() {
        vi === e && (vi = null);
        for (var t = 0; t < e.length; t += 3) {
          var l = e[t], a = e[t + 1], u = e[t + 2];
          if (typeof a != "function") {
            if (Rc(a || l) === null)
              continue;
            break;
          }
          var i = ya(l);
          i !== null && (e.splice(t, 3), t -= 3, Ds(
            i,
            {
              pending: !0,
              data: u,
              method: l.method,
              action: a
            },
            a,
            u
          ));
        }
      }
    ));
  }
  function Pa(e) {
    function t(v) {
      return hi(v, e);
    }
    Gl !== null && hi(Gl, e), Xl !== null && hi(Xl, e), Ll !== null && hi(Ll, e), $n.forEach(t), Fn.forEach(t);
    for (var l = 0; l < Ql.length; l++) {
      var a = Ql[l];
      a.blockedOn === e && (a.blockedOn = null);
    }
    for (; 0 < Ql.length && (l = Ql[0], l.blockedOn === null); )
      Xd(l), l.blockedOn === null && Ql.shift();
    if (l = (e.ownerDocument || e).$$reactFormReplay, l != null)
      for (a = 0; a < l.length; a += 3) {
        var u = l[a], i = l[a + 1], c = u[pt] || null;
        if (typeof i == "function")
          c || Qd(l);
        else if (c) {
          var r = null;
          if (i && i.hasAttribute("formAction")) {
            if (u = i, c = i[pt] || null)
              r = c.formAction;
            else if (Rc(u) !== null) continue;
          } else r = c.action;
          typeof r == "function" ? l[a + 1] = r : (l.splice(a, 3), a -= 3), Qd(l);
        }
      }
  }
  function Zd() {
    function e(i) {
      i.canIntercept && i.info === "react-transition" && i.intercept({
        handler: function() {
          return new Promise(function(c) {
            return u = c;
          });
        },
        focusReset: "manual",
        scroll: "manual"
      });
    }
    function t() {
      u !== null && (u(), u = null), a || setTimeout(l, 20);
    }
    function l() {
      if (!a && !navigation.transition) {
        var i = navigation.currentEntry;
        i && i.url != null && navigation.navigate(i.url, {
          state: i.getState(),
          info: "react-transition",
          history: "replace"
        });
      }
    }
    if (typeof navigation == "object") {
      var a = !1, u = null;
      return navigation.addEventListener("navigate", e), navigation.addEventListener("navigatesuccess", t), navigation.addEventListener("navigateerror", t), setTimeout(l, 100), function() {
        a = !0, navigation.removeEventListener("navigate", e), navigation.removeEventListener("navigatesuccess", t), navigation.removeEventListener("navigateerror", t), u !== null && (u(), u = null);
      };
    }
  }
  function Hc(e) {
    this._internalRoot = e;
  }
  pi.prototype.render = Hc.prototype.render = function(e) {
    var t = this._internalRoot;
    if (t === null) throw Error(f(409));
    var l = t.current, a = Ot();
    Ud(l, a, e, t, null, null);
  }, pi.prototype.unmount = Hc.prototype.unmount = function() {
    var e = this._internalRoot;
    if (e !== null) {
      this._internalRoot = null;
      var t = e.containerInfo;
      Ud(e.current, 2, null, e, null, null), Fu(), t[pa] = null;
    }
  };
  function pi(e) {
    this._internalRoot = e;
  }
  pi.prototype.unstable_scheduleHydration = function(e) {
    if (e) {
      var t = Wc();
      e = { blockedOn: null, target: e, priority: t };
      for (var l = 0; l < Ql.length && t !== 0 && t < Ql[l].priority; l++) ;
      Ql.splice(l, 0, e), l === 0 && Xd(e);
    }
  };
  var wd = d.version;
  if (wd !== "19.2.0")
    throw Error(
      f(
        527,
        wd,
        "19.2.0"
      )
    );
  Z.findDOMNode = function(e) {
    var t = e._reactInternals;
    if (t === void 0)
      throw typeof e.render == "function" ? Error(f(188)) : (e = Object.keys(e).join(","), Error(f(268, e)));
    return e = j(t), e = e !== null ? _(e) : null, e = e === null ? null : e.stateNode, e;
  };
  var T0 = {
    bundleType: 0,
    version: "19.2.0",
    rendererPackageName: "react-dom",
    currentDispatcherRef: S,
    reconcilerVersion: "19.2.0"
  };
  if (typeof __REACT_DEVTOOLS_GLOBAL_HOOK__ < "u") {
    var bi = __REACT_DEVTOOLS_GLOBAL_HOOK__;
    if (!bi.isDisabled && bi.supportsFiber)
      try {
        un = bi.inject(
          T0
        ), St = bi;
      } catch {
      }
  }
  return Pn.createRoot = function(e, t) {
    if (!E(e)) throw Error(f(299));
    var l = !1, a = "", u = Ff, i = Wf, c = If;
    return t != null && (t.unstable_strictMode === !0 && (l = !0), t.identifierPrefix !== void 0 && (a = t.identifierPrefix), t.onUncaughtError !== void 0 && (u = t.onUncaughtError), t.onCaughtError !== void 0 && (i = t.onCaughtError), t.onRecoverableError !== void 0 && (c = t.onRecoverableError)), t = Dd(
      e,
      1,
      !1,
      null,
      null,
      l,
      a,
      null,
      u,
      i,
      c,
      Zd
    ), e[pa] = t.current, pc(e), new Hc(t);
  }, Pn.hydrateRoot = function(e, t, l) {
    if (!E(e)) throw Error(f(299));
    var a = !1, u = "", i = Ff, c = Wf, r = If, v = null;
    return l != null && (l.unstable_strictMode === !0 && (a = !0), l.identifierPrefix !== void 0 && (u = l.identifierPrefix), l.onUncaughtError !== void 0 && (i = l.onUncaughtError), l.onCaughtError !== void 0 && (c = l.onCaughtError), l.onRecoverableError !== void 0 && (r = l.onRecoverableError), l.formState !== void 0 && (v = l.formState)), t = Dd(
      e,
      1,
      !0,
      t,
      l ?? null,
      a,
      u,
      v,
      i,
      c,
      r,
      Zd
    ), t.context = Rd(null), l = t.current, a = Ot(), a = Ei(a), u = zl(a), u.callback = null, Tl(l, u, a), l = a, t.current.lanes = l, cn(t, l), Wt(t), e[pa] = t.current, pc(e), new pi(t);
  }, Pn.version = "19.2.0", Pn;
}
var em;
function Y0() {
  if (em) return Yc.exports;
  em = 1;
  function s() {
    if (!(typeof __REACT_DEVTOOLS_GLOBAL_HOOK__ > "u" || typeof __REACT_DEVTOOLS_GLOBAL_HOOK__.checkDCE != "function"))
      try {
        __REACT_DEVTOOLS_GLOBAL_HOOK__.checkDCE(s);
      } catch (d) {
        console.error(d);
      }
  }
  return s(), Yc.exports = B0(), Yc.exports;
}
var cm = Y0();
function G0(s, d, b) {
  const f = s == null ? void 0 : s.one_click;
  return !(f != null && f.url) || !["rendering", "completed"].includes(f.status) || !d.getItem(b) ? null : (d.removeItem(b), f.url);
}
function X0(s, d) {
  var b;
  return !!(d && s && !["completed", "unchanged", "failed"].includes((b = s.one_click) == null ? void 0 : b.status));
}
function L0({ host: s }) {
  const [d, b] = T.useState(null), [f, E] = T.useState(!1), [z, O] = T.useState(!1), [U, M] = T.useState(!1), [j, _] = T.useState(""), y = T.useRef(!1), p = T.useRef(null), Y = s.closest(".ws-editing-assistant"), X = "one-click-optimization:" + s.dataset.film, I = new URLSearchParams(location.search).get("optimization_run"), ie = s.dataset.status + (I ? "?run=" + encodeURIComponent(I) : ""), J = async (H, h) => {
    const x = await fetch(H, { method: h ? "POST" : "GET", headers: { Accept: "application/json", ...h ? { "Content-Type": "application/json", "X-CSRF-TOKEN": s.dataset.csrf } : {} }, ...h ? { body: JSON.stringify(h) } : {} });
    let q;
    try {
      q = await x.json();
    } catch {
      throw Error("暂时无法读取，请刷新并检查登录状态。");
    }
    if (x.status >= 500) throw Error("暂未完成，请重新读取进度；原版保留。");
    if (!x.ok) throw Error(Object.values(q.errors || {}).flat()[0] || q.message || "暂未完成，请重新读取进度。");
    return q.run;
  }, ue = (H) => {
    b(H), E(!0);
    const h = G0(H, sessionStorage, X + ":active");
    h && location.assign(h);
  }, V = async () => {
    _("");
    try {
      ue(await J(d ? s.dataset.status + "?run=" + d.id : ie));
    } catch (H) {
      _(H.message);
    }
  };
  T.useEffect(() => {
    let H = !1;
    return J(ie).then((h) => {
      H || (b(h), E(!0), h != null && h.one_click && (sessionStorage.getItem(X + ":active") || ["queued", "applying", "rendering"].includes(h.one_click.status)) && (O(!0), ue(h)));
    }).catch((h) => {
      H || (_(h.message), O(!0));
    }), () => {
      H = !0;
    };
  }, []);
  const W = d == null ? void 0 : d.one_click, te = X0(d, z);
  T.useEffect(() => {
    if (!te || U) return;
    let H = !1, h = !1;
    const x = setInterval(async () => {
      if (!h) {
        h = !0;
        try {
          const q = await J(d ? s.dataset.status + "?run=" + d.id : ie);
          H || (_(""), ue(q));
        } catch (q) {
          H || _(q.message);
        } finally {
          h = !1;
        }
      }
    }, 5e3);
    return () => {
      H = !0, clearInterval(x);
    };
  }, [te, U, d == null ? void 0 : d.id]), T.useEffect(() => (Y == null || Y.classList.toggle("optimization-active", z), () => Y == null ? void 0 : Y.classList.remove("optimization-active")), [z]);
  const le = async () => {
    if (!(y.current || !f)) {
      if (O(!0), _(""), W) {
        sessionStorage.setItem(X + ":active", "1"), await V();
        return;
      }
      y.current = !0, M(!0), sessionStorage.setItem(X + ":active", "1");
      try {
        p.current = p.current || sessionStorage.getItem(X + ":request") || crypto.randomUUID(), sessionStorage.setItem(X + ":request", p.current);
        const H = await J(s.dataset.oneClick, { request_id: p.current, base_hash: s.dataset.hash, ...I || d != null && d.id ? { run_id: I || d.id } : {} });
        ue(H);
      } catch (H) {
        _(H.message + " 请求结果需要核对，请重新读取进度；不会自动重新分析。");
      } finally {
        y.current = !1, M(!1);
      }
    }
  }, P = () => {
    O(!1), sessionStorage.removeItem(X + ":active");
  }, F = (W == null ? void 0 : W.status) === "failed" || ["failed", "unknown"].includes(d == null ? void 0 : d.status), ee = (W == null ? void 0 : W.status) === "unchanged", fe = U ? "正在检查画面" : (W == null ? void 0 : W.status) === "applying" || (d == null ? void 0 : d.status) === "completed" && te ? "正在优化" : (W == null ? void 0 : W.status) === "rendering" ? "正在生成新版" : { queued: "等待后台检查", sampling: "正在检查画面", analysis: "正在分析画面与配方", validating: "正在核对优化方案" }[d == null ? void 0 : d.stage] || "正在读取优化进度";
  return /* @__PURE__ */ n.jsxs("div", { className: "optimization-ui", children: [
    /* @__PURE__ */ n.jsxs("div", { className: "opt-mode-switch", children: [
      /* @__PURE__ */ n.jsxs("button", { className: z ? "active" : "", onClick: le, disabled: U || !f, children: [
        "一键优化 ",
        /* @__PURE__ */ n.jsx("i", { className: "bi bi-stars" })
      ] }),
      /* @__PURE__ */ n.jsx("button", { className: z ? "" : "active", onClick: P, children: "项目对话 · Luna" })
    ] }),
    z ? /* @__PURE__ */ n.jsxs("div", { className: "opt-assistant", children: [
      /* @__PURE__ */ n.jsxs("div", { className: "opt-assistant-header", children: [
        /* @__PURE__ */ n.jsxs("div", { children: [
          /* @__PURE__ */ n.jsxs("h3", { children: [
            /* @__PURE__ */ n.jsx("i", { className: "bi bi-stars" }),
            " 一键优化"
          ] }),
          /* @__PURE__ */ n.jsx("p", { children: "AI 负责检查与制作，你只需查看新版。" })
        ] }),
        /* @__PURE__ */ n.jsx("span", { className: "opt-badge", children: "原版保留" })
      ] }),
      /* @__PURE__ */ n.jsxs("div", { className: "opt-intro", role: "status", "aria-live": "polite", children: [
        /* @__PURE__ */ n.jsx("div", { className: "opt-spark", children: /* @__PURE__ */ n.jsx("i", { className: `bi bi-${F ? "exclamation-circle" : ee ? "check-circle" : "hourglass-split"}` }) }),
        /* @__PURE__ */ n.jsx("h3", { children: F ? "这次优化没有完成" : ee ? "本次保留当前版本" : (W == null ? void 0 : W.status) === "completed" ? "AI 优化版已完成" : fe }),
        /* @__PURE__ */ n.jsx("p", { children: F ? (W == null ? void 0 : W.error) || (d == null ? void 0 : d.error) || "原版保留，没有自动重新分析。" : ee ? "没有发现能够可靠自动执行的改动，保留当前版本。" : "后台会继续处理，完成后自动显示 AI 优化版。可以刷新或切回项目对话。" }),
        (W == null ? void 0 : W.url) && /* @__PURE__ */ n.jsxs("a", { className: "opt-primary", href: W.url, children: [
          "查看 AI 优化版 ",
          /* @__PURE__ */ n.jsx("i", { className: "bi bi-arrow-right" })
        ] }),
        /* @__PURE__ */ n.jsx("small", { children: "完整口播、人物与商品事实保留，不会自动发布。没有可靠依据的改动会跳过。" })
      ] })
    ] }) : /* @__PURE__ */ n.jsxs("div", { className: "opt-intro compact-intro", children: [
      /* @__PURE__ */ n.jsx("h3", { children: "让 AI 完成检查与优化" }),
      /* @__PURE__ */ n.jsx("p", { children: "自动检查画面、选择可靠的优化并制作新版。你只需看看结果，原版始终保留。" }),
      /* @__PURE__ */ n.jsxs("button", { className: "opt-primary", disabled: U || !f, onClick: le, children: [
        (W == null ? void 0 : W.status) === "completed" ? "查看 AI 优化版" : W ? "查看优化状态" : "一键优化",
        " ",
        /* @__PURE__ */ n.jsx("i", { className: "bi bi-arrow-right" })
      ] }),
      /* @__PURE__ */ n.jsx("small", { children: "点击即开始检查与本地制作；已有检查会复用，新检查可能产生分析费用。" })
    ] }),
    j && /* @__PURE__ */ n.jsxs("p", { className: "opt-error", role: "alert", children: [
      j,
      /* @__PURE__ */ n.jsx("button", { className: "opt-link", onClick: V, children: "重新读取进度" })
    ] })
  ] });
}
function Q0() {
  const s = document.querySelector("[data-film-optimization]");
  s && cm.createRoot(s).render(/* @__PURE__ */ n.jsx(L0, { host: s }));
}
const Z0 = (s) => Array.from(s).reduce((d, b) => d + (/[^\x00-\xff]/.test(b) ? 2 : 1), 0);
function w0(s) {
  const d = s.words || [], b = d.map((O) => O.text).join("");
  let f = 0;
  const E = d.map((O) => {
    const U = f;
    return f += O.text.length, { ...O, a: U, b: f };
  }), z = [];
  for (const O of new Intl.Segmenter(void 0, { granularity: "word" }).segment(b)) {
    const U = E.filter((y) => y.a < O.index + O.segment.length && y.b > O.index);
    if (!U.length) continue;
    const M = Math.max(0, Math.min(...U.map((y) => y.start_frame)) - s.in_frame), j = Math.min(s.frames, Math.max(...U.map((y) => y.end_frame)) - s.in_frame);
    if (j <= M) continue;
    if (!O.isWordLike && z.length) {
      z.at(-1).text += O.segment, z.at(-1).end_frame = Math.max(z.at(-1).end_frame, j);
      continue;
    }
    const _ = z.at(-1);
    _ && _.start_frame === M && _.end_frame === j ? _.text += O.segment : z.push({ text: O.segment, start_frame: M, end_frame: j });
  }
  return z;
}
function V0(s) {
  const d = [];
  let b = [];
  for (const f of w0(s))
    b.length && Z0(b.map((E) => E.text).join("") + f.text) > 28 && (d.push(b), b = []), b.push(f), /[，。！？；,.!?;]\s*$/.test(f.text) && (d.push(b), b = []);
  return b.length && d.push(b), d.map((f, E) => {
    var z;
    return { words: f, start: f[0].start_frame, end: Math.min(s.frames, ((z = d[E + 1]) == null ? void 0 : z[0].start_frame) ?? f.at(-1).end_frame + 8) };
  });
}
function K0(s, d, b) {
  const f = V0(s), E = f.find((U) => b >= U.start && b < U.end);
  if (!E) return null;
  let z = -1;
  E.words.forEach((U, M) => {
    b >= U.start_frame && b < U.end_frame && (z = M);
  });
  const O = Math.min(1, Math.max(0, (b - E.start) / 4));
  return { words: E.words, active: z, opacity: d.mode === "phrase" ? O : 1, offset: d.mode === "phrase" ? (1 - O) * 12 : 0 };
}
function J0(s) {
  const d = (b) => String(b || "").replace(/[\p{P}\p{Z}\s]/gu, "").toLowerCase();
  return !!s.original_subtitle && d(s.original_subtitle) !== d(s.spoken_text);
}
function tn({ scene: s, settings: d, frame: b, original: f = !1, hideCaptions: E = !1 }) {
  const z = d.scope === "all" || d.scene === s.number, O = f || !z, U = O ? null : K0(s, d, b), M = { small: 48, medium: 60, large: 72 }[d.size], j = { position: "absolute", left: 54, right: 70, bottom: d.position === "raised" ? 300 : 190, color: "#fff", fontFamily: "'Microsoft YaHei',Arial,sans-serif", fontSize: M, fontWeight: 700, textAlign: "center", lineHeight: 1.5, textShadow: "0 2px 6px #000,0 0 5px #000", whiteSpace: "pre-wrap", overflowWrap: "anywhere" };
  return /* @__PURE__ */ n.jsxs("div", { style: { position: "absolute", inset: 0, pointerEvents: "none" }, children: [
    !E && (O ? /* @__PURE__ */ n.jsx("div", { style: { ...j, bottom: 170, fontSize: 30 }, children: s.original_subtitle }) : /* @__PURE__ */ n.jsxs(n.Fragment, { children: [
      J0(s) && /* @__PURE__ */ n.jsx("div", { style: { ...j, bottom: d.position === "raised" ? 560 : 450, fontSize: 28, lineHeight: 1.4 }, children: s.original_subtitle }),
      U && /* @__PURE__ */ n.jsx("div", { style: { ...j, opacity: U.opacity, transform: `translateY(${U.offset}px)` }, children: /* @__PURE__ */ n.jsx("span", { style: d.mode === "phrase" ? { background: "#163c2bdd", borderRadius: 12, padding: "8px 16px", boxDecorationBreak: "clone" } : {}, children: U.words.map((_, y) => /* @__PURE__ */ n.jsx("span", { style: d.mode === "highlight" && y === U.active ? { background: d.color, color: "#173d2a", borderRadius: 5, padding: "2px 4px", textShadow: "none" } : {}, children: _.text }, y)) }) })
    ] })),
    s.cta && !s.original_subtitle.includes(s.cta) && b >= Math.max(0, s.frames - s.cta_hold_frames) && /* @__PURE__ */ n.jsx("div", { style: { ...j, bottom: 78, fontSize: 30 }, children: s.cta })
  ] });
}
const k0 = ({ name: s }) => /* @__PURE__ */ n.jsx("svg", { width: "25", height: "25", viewBox: "0 0 24 24", fill: "none", stroke: "currentColor", strokeWidth: "1.8", style: { verticalAlign: "middle", marginRight: 8 }, "aria-hidden": "true", children: s === "whatsapp" ? /* @__PURE__ */ n.jsxs(n.Fragment, { children: [
  /* @__PURE__ */ n.jsx("path", { d: "M20 11.5a8 8 0 0 1-12 7L3 20l1.5-5A8 8 0 1 1 20 11.5Z" }),
  /* @__PURE__ */ n.jsx("path", { d: "M8 7c0 5 4 9 8 9l1-3-3-1-1 1c-1.5-.8-2.5-1.8-3-3l1-1-1-2Z" })
] }) : /* @__PURE__ */ n.jsx("path", { d: "M5 3 3 7c1 7 7 13 14 14l4-2-4-5-3 2c-3-1-5-3-6-6l2-3Z" }) });
function ln({ card: s }) {
  if (!s) return null;
  const d = s.position === "top-right" ? null : s.position === "top-center" ? 180 : 54, b = s.position === "top-right" ? 54 : null;
  return /* @__PURE__ */ n.jsxs("div", { className: `information-layer information-${s.type}`, style: { position: "absolute", left: d, right: b, top: 66, width: 360, color: "#163c2a", background: s.type === "contact" ? "#123c2beF" : "#fbfcf6f5", borderRadius: 16, padding: "20px 22px", boxShadow: "0 5px 20px #001a1822", border: "1px solid #dce7ce", fontFamily: "'Microsoft YaHei',Arial,sans-serif", lineHeight: 1.45 }, children: [
    s.type === "step" && /* @__PURE__ */ n.jsx("span", { style: { display: "inline-flex", alignItems: "center", justifyContent: "center", height: 36, minWidth: 36, padding: "0 8px", borderRadius: 20, background: "#d2f36b", fontSize: 23, fontWeight: 700, marginBottom: 8 }, children: s.step }),
    /* @__PURE__ */ n.jsx("div", { style: { fontSize: 32, fontWeight: 700, color: s.type === "contact" ? "#f4f7e9" : "#143c28", overflowWrap: "anywhere" }, children: s.title }),
    s.body && /* @__PURE__ */ n.jsx("div", { style: { fontSize: 24, marginTop: 7, color: s.type === "contact" ? "#d9e8c4" : "#64765b", overflowWrap: "anywhere" }, children: s.body }),
    s.type === "contact" && s.channel !== "none" && s.contact.trim() && /* @__PURE__ */ n.jsxs("div", { style: { marginTop: 12, fontSize: 25, color: "#d2f36b", overflowWrap: "anywhere" }, children: [
      /* @__PURE__ */ n.jsx(k0, { name: s.channel === "whatsapp" ? "whatsapp" : "telephone" }),
      " ",
      s.contact
    ] })
  ] });
}
function eu({ layers: s = [], frame: d }) {
  return /* @__PURE__ */ n.jsx(n.Fragment, { children: s.filter((b) => b.enabled && b.url && d >= b.start_frame && d < b.end_frame).map((b) => /* @__PURE__ */ n.jsx("div", { style: { position: "absolute", left: `${b.x}%`, top: `${b.y}%`, width: `${b.width}%`, transform: "translate(-50%,-50%)", borderRadius: b.radius, border: `${b.border}px solid ${b.color}`, background: "#fff", boxShadow: "0 3px 12px #001a1833", boxSizing: "border-box", overflow: "hidden" }, children: /* @__PURE__ */ n.jsx("img", { src: b.url, style: { width: "100%", height: "auto", display: "block" } }) }, b.id)) });
}
const wl = (s, d, b) => Math.max(d, Math.min(b, s)), Qc = (s) => s * s * (3 - 2 * s);
function ha(s, d, b) {
  if (!s || s.mode === "none" || d < s.start_frame || d >= s.end_frame) return { scale: 1, x: 0, y: 0 };
  const f = Math.max(1, s.end_frame - s.start_frame), E = wl((d - s.start_frame) / Math.max(1, f - 1), 0, 1), z = Math.min(8, Math.floor(f / 4)), O = s.mode === "fixed" ? Qc(wl((d - s.start_frame) / z, 0, 1)) : Qc(E), U = s.end_frame < b ? Qc(wl((s.end_frame - 1 - d) / z, 0, 1)) : 1, M = 1 + (wl(s.zoom, 1, 1.3) - 1) * O * U;
  return { scale: M, x: wl(0.5 - M * wl(s.x / 100, 0, 1), 1 - M, 0), y: wl(0.5 - M * wl(s.y / 100, 0, 1), 1 - M, 0) };
}
const yi = (s) => Array.isArray(s) && s.length === 4 && s.every(Number.isFinite) && s[0] >= -1e-8 && s[1] >= -1e-8 && s[2] > 0 && s[3] > 0 && s[0] + s[2] <= 1 + 1e-8 && s[1] + s[3] <= 1 + 1e-8, $0 = (s, d) => s[0] < d[0] + d[2] && s[0] + s[2] > d[0] && s[1] < d[1] + d[3] && s[1] + s[3] > d[1], tm = (s, d) => [s[0] * d.scale + d.x, s[1] * d.scale + d.y, s[2] * d.scale, s[3] * d.scale];
function rm(s, d) {
  const b = { ...s, ...d };
  return Object.keys(d).some((f) => ["title", "body", "scene_number", "type", "contact", "channel"].includes(f)) && (delete b.semantic, b.source_refs = ["user"], b.source = "用户采用的文案"), s.grounding && Object.keys(d).some((f) => s[f] !== d[f]) && delete b.grounding, b;
}
function F0(s = [], d = [], b) {
  const f = [{ start_frame: 0, end_frame: 2880, bbox: [0, 0.7, 1, 0.3] }];
  for (const E of s.filter((z) => z.scene_number === b)) {
    const z = 56 + Math.max(1, Math.ceil([...E.title || ""].length / 9)) * 32 * 1.45 + 7 + Math.ceil([...E.body || ""].length / 13) * 24 * 1.45 + (E.type === "step" ? 44 : 0) + (E.channel !== "none" && E.contact ? 12 + Math.ceil([...E.contact].length / 12) * 25 * 1.45 : 0);
    f.push({ start_frame: E.start_frame, end_frame: E.end_frame, bbox: [E.position === "top-right" ? 0.36 : E.position === "top-center" ? 0.25 : 0.075, 66 / 1280, 406 / 720, Math.min(0.45, z / 1280)] });
  }
  for (const E of d.filter((z) => z.scene_number === b && z.enabled !== !1)) {
    const z = E.width / 100, O = z * 720 / (E.ratio || 1.5) / 1280;
    f.push({ start_frame: E.start_frame, end_frame: E.end_frame, bbox: [E.x / 100 - z / 2, E.y / 100 - O / 2, z, O] });
  }
  return f;
}
function W0(s, d, b) {
  const f = s.size / 100, E = 6e-3;
  if (s.kind !== "arrow") return [d - f / 2 - E, b - f / 2 - E, f + E * 2, f + E * 2];
  const z = (s.angle || 0) * Math.PI / 180, O = Math.cos(z), U = Math.sin(z), M = -U, j = O, _ = [[d - O * f, b - U * f], [d, b], [d + O * 0.012, b + U * 0.012], [d - O * 0.048 + M * 0.03, b - U * 0.048 + j * 0.03], [d - O * 0.048 - M * 0.03, b - U * 0.048 - j * 0.03]], y = _.map((Y) => Y[0]), p = _.map((Y) => Y[1]);
  return [Math.min(...y) - E, Math.min(...p) - E, Math.max(...y) - Math.min(...y) + 2 * E, Math.max(...p) - Math.min(...p) + 2 * E];
}
function I0(s, d, b, f, E = null) {
  var X;
  if (!s || f < s.start_frame || f >= s.end_frame) return null;
  const z = s.grounding;
  if (!z) return s;
  if (!d || d.number !== z.scene_number || d.raw_sha256 !== z.source_sha256 || !((X = z.intervals) != null && X.some(([I, ie]) => f >= I && f < ie))) return null;
  const O = z.points;
  if (!(O != null && O.length) || f < O[0].frame || f > O.at(-1).frame) return null;
  let U = O[0], M = O.at(-1);
  for (let I = 1; I < O.length; I++) if (f <= O[I].frame) {
    U = O[I - 1], M = O[I];
    break;
  }
  if (!yi(U.bbox) || !yi(M.bbox) || z.mode === "moving" && M.frame - U.frame > 12) return null;
  const j = M.frame === U.frame ? 0 : (f - U.frame) / (M.frame - U.frame), _ = U.bbox.map((I, ie) => I + (M.bbox[ie] - I) * j), y = ha(b, f, d.frames), p = tm(_, y);
  if (!yi(p)) return null;
  const Y = [...(E ?? z.avoidance ?? []).filter((I) => f >= I.start_frame && f < I.end_frame).map((I) => I.bbox), ...[...U.people || [], ...M.people || []].map((I) => tm(I, y))];
  s.size / 100;
  for (const [I, ie] of [[0.5, 0.5], [0.75, 0.5], [0.75, 0.3], [0.75, 0.7], [0.3, 0.5], [0.5, 0.3], [0.5, 0.7]]) {
    const J = p[0] + p[2] * I, ue = p[1] + p[3] * ie;
    if (J < 0.05 || J > 0.95 || ue < 0.05 || ue > 0.95) continue;
    const V = W0(s, J, ue);
    if (!(!yi(V) || Y.some((W) => $0(V, W))) && !(s.kind !== "arrow" && (V[0] < p[0] || V[1] < p[1] || V[0] + V[2] > p[0] + p[2] || V[1] + V[3] > p[1] + p[3])))
      return { ...s, x: J * 100, y: ue * 100, targetBox: p };
  }
  return null;
}
function an({ effect: s, frame: d, scene: b, framing: f, cards: E = [], overlays: z = [] }) {
  const O = T.useId().replace(/:/g, "");
  if (s = I0(s, b, f, d, F0(E, z, b == null ? void 0 : b.number)), !s || d < s.start_frame || d >= s.end_frame) return null;
  const { kind: U, x: M, y: j, size: _, angle: y, color: p, strength: Y } = s;
  return /* @__PURE__ */ n.jsxs("svg", { style: { position: "absolute", inset: 0, width: "100%", height: "100%", pointerEvents: "none" }, viewBox: "0 0 100 100", preserveAspectRatio: "none", "aria-hidden": "true", children: [
    /* @__PURE__ */ n.jsxs("defs", { children: [
      /* @__PURE__ */ n.jsxs("mask", { id: `spot-${O}`, children: [
        /* @__PURE__ */ n.jsx("rect", { width: "100", height: "100", fill: "white" }),
        /* @__PURE__ */ n.jsx("ellipse", { cx: M, cy: j, rx: _ / 2, ry: _ / 2, fill: "black" })
      ] }),
      /* @__PURE__ */ n.jsx("marker", { id: `arrow-${O}`, markerWidth: "5", markerHeight: "5", refX: "4", refY: "2.5", orient: "auto", children: /* @__PURE__ */ n.jsx("path", { d: "M0,0 L5,2.5 L0,5", fill: p }) })
    ] }),
    U === "spot" ? /* @__PURE__ */ n.jsxs(n.Fragment, { children: [
      /* @__PURE__ */ n.jsx("rect", { width: "100", height: "100", fill: "black", opacity: Y / 100, mask: `url(#spot-${O})` }),
      /* @__PURE__ */ n.jsx("ellipse", { cx: M, cy: j, rx: _ / 2, ry: _ / 2, stroke: p, strokeWidth: ".5", fill: "none" })
    ] }) : U === "circle" ? /* @__PURE__ */ n.jsx("ellipse", { cx: M, cy: j, rx: _ / 2, ry: _ / 2, stroke: p, strokeWidth: "1.2", fill: "none" }) : /* @__PURE__ */ n.jsx("line", { x1: M - Math.cos(y * Math.PI / 180) * _, y1: j - Math.sin(y * Math.PI / 180) * _, x2: M, y2: j, stroke: p, strokeWidth: "1.2", markerEnd: `url(#arrow-${O})` })
  ] });
}
function P0({ scene: s }) {
  const [d, b] = T.useState(!1);
  return d ? /* @__PURE__ */ n.jsx("i", { className: "bi bi-film" }) : /* @__PURE__ */ n.jsx("video", { src: s.raw_url, muted: !0, playsInline: !0, preload: "metadata", "aria-label": `第${s.number}段真实画面`, onLoadedMetadata: (f) => {
    f.currentTarget.currentTime = ((s.in_frame || 0) + Math.min(24, s.frames - 1)) / 24;
  }, onError: () => b(!0) });
}
function nn({ rows: s, selected: d, onSelect: b, label: f = "选择分镜" }) {
  return /* @__PURE__ */ n.jsx("div", { className: "speed-scenes", role: "group", "aria-label": f, children: s.map((E) => /* @__PURE__ */ n.jsxs("button", { type: "button", className: `speed-scene ${E.number === d ? "selected" : ""}`, "aria-pressed": E.number === d, onClick: () => b(E), children: [
    /* @__PURE__ */ n.jsxs("div", { className: "speed-scene-image", children: [
      /* @__PURE__ */ n.jsx(P0, { scene: E }),
      /* @__PURE__ */ n.jsxs("span", { children: [
        "第",
        E.number,
        "段"
      ] }),
      E.number === d && /* @__PURE__ */ n.jsx("i", { className: "bi bi-check-circle-fill" })
    ] }),
    /* @__PURE__ */ n.jsx("strong", { title: E.title, children: (E.spoken_text || E.title).split(/[，。？！]/)[0] }),
    /* @__PURE__ */ n.jsxs("small", { children: [
      (E.frames / 24).toFixed(2),
      "秒 · ",
      (E.start_frame / 24).toFixed(2),
      "–",
      (E.end_frame / 24).toFixed(2),
      "秒"
    ] })
  ] }, E.clip_id || E.number)) });
}
function ev({ card: s, cards: d, frames: b, onChange: f }) {
  const E = T.useRef(null), z = d.filter((y) => y.id !== s.id && y.scene_number === s.scene_number), O = Math.max(0, ...z.filter((y) => y.end_frame <= s.start_frame).map((y) => y.end_frame)), U = Math.min(b, ...z.filter((y) => y.start_frame >= s.end_frame).map((y) => y.start_frame)), M = (y) => y === "start_frame" ? [O, s.end_frame - 12] : [s.start_frame + 12, U], j = (y, p) => {
    const [Y, X] = M(y);
    Y <= X && f(y, Math.max(Y, Math.min(X, Math.round(p))));
  }, _ = (y, p) => {
    const Y = E.current.getBoundingClientRect();
    j(p, (y.clientX - Y.left) / Y.width * b);
  };
  return /* @__PURE__ */ n.jsxs(n.Fragment, { children: [
    /* @__PURE__ */ n.jsxs("div", { className: "info-range speed-range", ref: E, role: "group", "aria-label": "拖动调整变速区间", children: [
      /* @__PURE__ */ n.jsx("div", { className: "info-range-rail" }),
      /* @__PURE__ */ n.jsx("div", { className: "info-range-fill", style: { left: `${s.start_frame / b * 100}%`, width: `${(s.end_frame - s.start_frame) / b * 100}%` } }),
      [["start_frame", "开始"], ["end_frame", "结束"]].map(([y, p]) => /* @__PURE__ */ n.jsx("button", { type: "button", className: "info-range-handle", role: "slider", "aria-label": `拖动${p}时间`, "aria-valuemin": M(y)[0], "aria-valuemax": M(y)[1], "aria-valuenow": s[y], "aria-valuetext": `${(s[y] / 24).toFixed(2)}秒`, style: { left: `${s[y] / b * 100}%` }, onPointerDown: (Y) => {
        Y.preventDefault(), Y.currentTarget.setPointerCapture(Y.pointerId), _(Y, y);
      }, onPointerMove: (Y) => {
        Y.currentTarget.hasPointerCapture(Y.pointerId) && _(Y, y);
      }, onPointerUp: (Y) => {
        Y.currentTarget.hasPointerCapture(Y.pointerId) && Y.currentTarget.releasePointerCapture(Y.pointerId);
      }, onKeyDown: (Y) => {
        const X = { ArrowLeft: -1, ArrowDown: -1, ArrowRight: 1, ArrowUp: 1, PageDown: -24, PageUp: 24 }[Y.key];
        X !== void 0 ? (Y.preventDefault(), j(y, s[y] + X)) : (Y.key === "Home" || Y.key === "End") && (Y.preventDefault(), j(y, M(y)[Y.key === "Home" ? 0 : 1]));
      }, children: /* @__PURE__ */ n.jsx("span", {}) }, y))
    ] }),
    /* @__PURE__ */ n.jsx("div", { className: "info-range-help", children: "拖左端调整开始，拖右端调整结束 · 最短0.50秒" })
  ] });
}
const tv = (s, d) => {
  var b, f;
  return !!((b = s.spoken_text) != null && b.trim()) && !((f = s.words) != null && f.length) || (s.words || []).some((E) => /[\p{L}\p{N}]/u.test(E.text) && E.start_frame < d.end_frame && E.end_frame > d.start_frame);
};
function fm(s, d) {
  const b = Math.max(0, s), f = d.start_frame, E = d.end_frame, z = d.rate;
  return b <= f ? b : b < E ? f + (b - f) / z : b - (E - f) + (E - f) / z;
}
const Zc = (s, d) => Math.round(fm(s.frames, d)), xi = (s, d, b) => !b && s >= d.start_frame && s < d.end_frame ? d.rate : 1, ot = (s) => (s / 24).toFixed(2);
function lv({ scene: s, plan: d, blocked: b, original: f, frame: E, setFrame: z, epoch: O, playKey: U, onError: M, sourceUrl: j, visual: _ }) {
  const y = T.useRef(), [p, Y] = T.useState(0.25), X = T.useRef(), [I, ie] = T.useState(!1), [J, ue] = T.useState(1), [V, W] = T.useState(!1), te = T.useRef({ scene: s, plan: d, blocked: b, original: f });
  te.current = { scene: s, plan: d, blocked: b, original: f, sourceUrl: j }, T.useEffect(() => {
    const H = new ResizeObserver((h) => Y(h[0].contentRect.width / 720));
    return H.observe(y.current), () => H.disconnect();
  }, []);
  const le = (H) => {
    var x;
    const h = Math.max(0, Math.min(s.frames - 1, H));
    z(h), ((x = X.current) == null ? void 0 : x.readyState) >= 1 && (X.current.currentTime = ((j === s.raw_url ? s.in_frame : s.start_frame) + h) / 24);
  };
  T.useEffect(() => {
    const H = X.current;
    let h;
    const x = () => {
      const S = te.current;
      let Z = Math.round(H.currentTime * 24) - (S.sourceUrl === S.scene.raw_url ? S.scene.in_frame : S.scene.start_frame);
      Z >= S.scene.frames && (H.pause(), Z = S.scene.frames - 1), Z = Math.max(0, Z);
      const ne = xi(Z, S.plan, S.blocked || S.original);
      H.playbackRate = ne, ue(ne), z(Z), H.paused || (h = requestAnimationFrame(x));
    }, q = () => {
      ie(!0), h = requestAnimationFrame(x);
    }, L = () => {
      ie(!1), cancelAnimationFrame(h);
    };
    return H.preservesPitch = !0, H.addEventListener("play", q), H.addEventListener("pause", L), () => {
      cancelAnimationFrame(h), H.pause(), H.playbackRate = 1, H.removeEventListener("play", q), H.removeEventListener("pause", L);
    };
  }, []), T.useEffect(() => {
    X.current.pause(), X.current.playbackRate = 1, ue(1), le(E);
  }, [s.number, O]), T.useEffect(() => {
    const H = xi(E, d, b || f);
    X.current.playbackRate = H, ue(H);
  }, [E, d.rate, d.start_frame, d.end_frame, b, f]), T.useEffect(() => {
    U && (le(d.start_frame), X.current.playbackRate = xi(d.start_frame, d, b || f), X.current.play().catch(() => M("请点击预览中的播放按钮。")));
  }, [U]);
  const P = ha(_ == null ? void 0 : _.framing.find((H) => H.scene_number === s.number), E, s.frames), F = _ == null ? void 0 : _.cards.find((H) => H.scene_number === s.number && E >= H.start_frame && E < H.end_frame), ee = _ == null ? void 0 : _.recipes.find((H) => H.scene.number === s.number), fe = F != null && F.replace_product_info ? { ...s, original_subtitle: s.spoken_text } : s;
  return /* @__PURE__ */ n.jsxs(n.Fragment, { children: [
    /* @__PURE__ */ n.jsxs("div", { className: "pack-player compact-player", children: [
      /* @__PURE__ */ n.jsxs("div", { className: "portrait-stage", ref: y, children: [
        /* @__PURE__ */ n.jsx("video", { style: { transformOrigin: "0 0", transform: `translate(${P.x * 100}%,${P.y * 100}%) scale(${P.scale})` }, ref: X, src: j, playsInline: !0, muted: V, preload: "auto", onLoadedMetadata: () => le(E), onError: () => M("现有成片未能读取，请刷新样本。") }),
        _ && /* @__PURE__ */ n.jsxs(n.Fragment, { children: [
          /* @__PURE__ */ n.jsx(an, { cards: _.cards, overlays: _.overlays, scene: s, framing: _.framing.find((H) => H.scene_number === s.number), effect: _.emphasis.find((H) => H.scene_number === s.number), frame: E }),
          /* @__PURE__ */ n.jsxs("div", { style: { position: "absolute", left: 0, top: 0, width: 720, height: 1280, transform: `scale(${p})`, transformOrigin: "top left" }, children: [
            /* @__PURE__ */ n.jsx(tn, { scene: fe, settings: (ee == null ? void 0 : ee.settings) || _.settings, original: (ee == null ? void 0 : ee.original) || !1, frame: E }),
            /* @__PURE__ */ n.jsx(ln, { card: F }),
            /* @__PURE__ */ n.jsx(eu, { layers: _.overlays.filter((H) => H.scene_number === s.number), frame: E })
          ] })
        ] }),
        /* @__PURE__ */ n.jsx("span", { className: "speed-live-badge", children: f ? "原速对比" : `${J.toFixed(1)}×` })
      ] }),
      /* @__PURE__ */ n.jsxs("div", { className: "transport", children: [
        /* @__PURE__ */ n.jsx("button", { "aria-label": I ? "暂停变速预览" : "播放变速预览", onClick: () => {
          I ? X.current.pause() : (E >= s.frames - 1 && le(0), X.current.playbackRate = xi(E, d, b || f), X.current.play().catch(() => M("请再点击播放。")));
        }, children: /* @__PURE__ */ n.jsx("i", { className: `bi bi-${I ? "pause-fill" : "play-fill"}` }) }),
        /* @__PURE__ */ n.jsxs("span", { children: [
          (E / 24).toFixed(1),
          " / ",
          (s.frames / 24).toFixed(1)
        ] }),
        /* @__PURE__ */ n.jsx("input", { type: "range", "aria-label": "变速预览播放进度", min: "0", max: s.frames - 1, value: E, onChange: (H) => {
          X.current.pause(), le(+H.target.value);
        } }),
        /* @__PURE__ */ n.jsx("button", { "aria-label": V ? "开启变速试听声音" : "静音变速试听", onClick: () => W(!V), children: /* @__PURE__ */ n.jsx("i", { className: `bi bi-volume-${V ? "mute" : "up"}` }) })
      ] })
    ] }),
    /* @__PURE__ */ n.jsx("span", { className: "info-live-state", children: f ? "按原来的速度播放" : b ? "口播保护中 · 当前保持1×" : `片段时间 ${ot(E)}秒 → 方案时间 ${ot(fm(E, d))}秒` })
  ] });
}
function av({ rows: s, draft: d, setDraft: b, totalDuration: f, outroDuration: E, onTouch: z = () => {
}, visual: O, children: U }) {
  const M = s.find((x) => x.number === d.scene_number) || s[0], j = tv(M, d), _ = d.protect && j && d.rate !== 1, [y, p] = T.useState(24), [Y, X] = T.useState(0), [I, ie] = T.useState(0), [J, ue] = T.useState(!1), [V, W] = T.useState(""), te = Zc(M, d) - M.frames, le = Math.round(f * 24) + te, P = (x) => {
    z(), b((q) => ({ ...q, ...x })), ue(!1);
  }, F = (x) => {
    p(Math.max(0, Math.min(M.frames - 1, x))), X((q) => q + 1);
  }, ee = (x, q) => {
    P({ [x]: q }), F(x === "start_frame" ? q : q - 1);
  }, fe = () => {
    P({ protect: !1 }), ue(!1), ie((x) => x + 1);
  }, H = /* @__PURE__ */ n.jsxs(n.Fragment, { children: [
    /* @__PURE__ */ n.jsxs("div", { className: "eyebrow", children: [
      "边看边调整 ",
      /* @__PURE__ */ n.jsx("span", { children: "源片变速试听" })
    ] }),
    /* @__PURE__ */ n.jsx(lv, { scene: M, plan: d, blocked: _, original: J, frame: y, setFrame: p, epoch: Y, playKey: I, onError: W, sourceUrl: M.raw_url, visual: O }),
    /* @__PURE__ */ n.jsxs("div", { className: "preview-shortcuts", children: [
      /* @__PURE__ */ n.jsx("button", { className: "none-button", onClick: () => {
        ue(!J), F(d.start_frame);
      }, children: J ? "看变速效果" : "对比原速" }),
      /* @__PURE__ */ n.jsx("button", { className: "none-button", onClick: () => {
        F(d.start_frame), ie((x) => x + 1);
      }, children: "重播区间" })
    ] }),
    /* @__PURE__ */ n.jsxs("div", { className: "length-note", children: [
      /* @__PURE__ */ n.jsx("i", { className: "bi bi-clock" }),
      /* @__PURE__ */ n.jsxs("div", { children: [
        "第",
        M.number,
        "段 · 原片 ",
        ot(M.frames),
        "秒",
        /* @__PURE__ */ n.jsxs("strong", { children: [
          "方案 ",
          ot(Zc(M, d)),
          "秒"
        ] })
      ] })
    ] }),
    /* @__PURE__ */ n.jsx("p", { className: "preview-footnote", children: "预览同步展示当前包装与原声变速。背景音乐在生成新预览时独立混音，保持原速；品牌片尾时长不变。" })
  ] }), h = /* @__PURE__ */ n.jsxs(n.Fragment, { children: [
    /* @__PURE__ */ n.jsxs("div", { className: "section-top", children: [
      /* @__PURE__ */ n.jsxs("div", { children: [
        /* @__PURE__ */ n.jsx("h3", { children: "把节奏，调到刚刚好" }),
        /* @__PURE__ */ n.jsx("p", { children: "选一小段，试听快一点或慢一点的效果。" })
      ] }),
      /* @__PURE__ */ n.jsx("span", { className: "sample-badge", children: "本地制作" })
    ] }),
    /* @__PURE__ */ n.jsxs("div", { className: "section-title", children: [
      /* @__PURE__ */ n.jsx("h4", { children: "选择要调整的分镜" }),
      /* @__PURE__ */ n.jsxs("span", { children: [
        s.length,
        "段实际素材 · 本次调整一段"
      ] })
    ] }),
    /* @__PURE__ */ n.jsx(nn, { rows: s, selected: M.number, label: "变速片段", onSelect: (x) => {
      P({ scene_number: x.number, start_frame: 0, end_frame: Math.min(48, x.frames), protect: !0 }), p(0), X((q) => q + 1);
    } }),
    /* @__PURE__ */ n.jsxs("details", { className: "speed-scene-details", children: [
      /* @__PURE__ */ n.jsx("summary", { children: "查看本段导演稿与口播" }),
      /* @__PURE__ */ n.jsx("p", { children: M.title }),
      /* @__PURE__ */ n.jsx("p", { children: M.spoken_text })
    ] }),
    /* @__PURE__ */ n.jsxs("div", { className: "info-timing", children: [
      /* @__PURE__ */ n.jsxs("div", { className: "section-title", children: [
        /* @__PURE__ */ n.jsx("h4", { children: "拖动选择变速范围" }),
        /* @__PURE__ */ n.jsxs("span", { children: [
          "片段内时间 · ",
          ot(M.frames),
          "秒"
        ] })
      ] }),
      /* @__PURE__ */ n.jsx("span", { className: "speed-draft-label", children: "片段内时间 · 采用后生成新版本" }),
      /* @__PURE__ */ n.jsx(ev, { card: { ...d, id: "speed" }, cards: [], frames: M.frames, onChange: ee }),
      /* @__PURE__ */ n.jsxs("div", { className: "speed-speech-track", "aria-label": "与变速范围联动的口播参考", children: [
        (M.words || []).filter((x) => /[\p{L}\p{N}]/u.test(x.text)).map((x, q) => /* @__PURE__ */ n.jsx("span", { className: x.start_frame < d.end_frame && x.end_frame > d.start_frame ? "in-range" : "", title: `${x.text} · ${ot(x.start_frame)}–${ot(x.end_frame)}秒`, style: { left: `${x.start_frame / M.frames * 100}%`, width: `${(x.end_frame - x.start_frame) / M.frames * 100}%` } }, q)),
        /* @__PURE__ */ n.jsx("div", { className: "speech-selection", style: { left: `${d.start_frame / M.frames * 100}%`, width: `${(d.end_frame - d.start_frame) / M.frames * 100}%` } })
      ] }),
      /* @__PURE__ */ n.jsxs("div", { className: "speed-track-key", children: [
        /* @__PURE__ */ n.jsxs("span", { children: [
          /* @__PURE__ */ n.jsx("i", {}),
          " 绿色口播 = 已落入变速范围"
        ] }),
        /* @__PURE__ */ n.jsx("span", { children: "拖动上方把手，口播同步高亮" })
      ] }),
      /* @__PURE__ */ n.jsxs("div", { className: "speed-selected-speech", "aria-live": "polite", children: [
        /* @__PURE__ */ n.jsx("b", { children: "选区内口播" }),
        /* @__PURE__ */ n.jsx("div", { children: (M.words || []).filter((x) => /[\p{L}\p{N}]/u.test(x.text)).map((x, q) => /* @__PURE__ */ n.jsx("span", { className: `${x.start_frame < d.end_frame && x.end_frame > d.start_frame ? "selected-word" : ""} ${y >= x.start_frame && y < x.end_frame ? "current-word" : ""}`, children: x.text }, q)) }),
        /* @__PURE__ */ n.jsxs("small", { children: [
          j ? "高亮词会随画面变速；含部分词时也会标出，请避免从词中间切入。" : "当前范围未覆盖已记录的口播。",
          d.protect && j && d.rate !== 1 ? " 口播保护开启，试听仍保持1×。" : ""
        ] })
      ] }),
      /* @__PURE__ */ n.jsxs("div", { className: "field-row", children: [
        /* @__PURE__ */ n.jsxs("label", { className: "field", children: [
          "开始（秒）",
          /* @__PURE__ */ n.jsx("input", { "aria-label": "变速开始秒数", type: "number", step: "0.05", value: ot(d.start_frame), onChange: (x) => ee("start_frame", Math.max(0, Math.min(d.end_frame - 12, Math.round(+x.target.value * 24)))) })
        ] }),
        /* @__PURE__ */ n.jsxs("label", { className: "field", children: [
          "结束（秒）",
          /* @__PURE__ */ n.jsx("input", { "aria-label": "变速结束秒数", type: "number", step: "0.05", value: ot(d.end_frame), onChange: (x) => ee("end_frame", Math.min(M.frames, Math.max(d.start_frame + 12, Math.round(+x.target.value * 24)))) })
        ] })
      ] }),
      /* @__PURE__ */ n.jsxs("div", { className: "info-time-caption", children: [
        /* @__PURE__ */ n.jsxs("span", { children: [
          "整片原位置 ",
          ot(M.start_frame + d.start_frame),
          "–",
          ot(M.start_frame + d.end_frame),
          "秒"
        ] }),
        /* @__PURE__ */ n.jsxs("b", { children: [
          "源区间 ",
          ot(d.end_frame - d.start_frame),
          "秒"
        ] })
      ] })
    ] }),
    /* @__PURE__ */ n.jsxs("div", { className: "section-title", children: [
      /* @__PURE__ */ n.jsx("h4", { children: "播放多快？" }),
      /* @__PURE__ */ n.jsx("span", { children: "区间之外保持1×" })
    ] }),
    /* @__PURE__ */ n.jsx("div", { className: "speed-options", role: "radiogroup", "aria-label": "变速倍率", children: [[0.5, "慢放"], [1, "正常"], [1.5, "轻快"], [2, "加速"]].map(([x, q]) => /* @__PURE__ */ n.jsxs("button", { className: `none-button ${d.rate === x ? "selected" : ""}`, role: "radio", "aria-checked": d.rate === x, onClick: () => {
      P({ rate: x }), F(d.start_frame);
    }, children: [
      /* @__PURE__ */ n.jsxs("b", { children: [
        x,
        "×"
      ] }),
      /* @__PURE__ */ n.jsx("small", { children: q })
    ] }, x)) }),
    /* @__PURE__ */ n.jsxs("div", { className: "speed-protect", children: [
      /* @__PURE__ */ n.jsxs("div", { children: [
        /* @__PURE__ */ n.jsxs("b", { children: [
          /* @__PURE__ */ n.jsx("i", { className: "bi bi-shield-check" }),
          " 默认保护口播"
        ] }),
        /* @__PURE__ */ n.jsx("small", { children: "区间涉及讲话时，先保持原速。" })
      ] }),
      /* @__PURE__ */ n.jsxs("label", { className: "switch-label", children: [
        /* @__PURE__ */ n.jsx("input", { type: "checkbox", checked: d.protect, onChange: (x) => P({ protect: x.target.checked }) }),
        "保护口播"
      ] })
    ] }),
    _ ? /* @__PURE__ */ n.jsxs("div", { className: "speed-warning", role: "status", children: [
      /* @__PURE__ */ n.jsx("b", { children: "这段包含口播，预览目前保持1×" }),
      /* @__PURE__ */ n.jsx("p", { children: "可缩小区间避开讲话；或先听听口播随画面变速的效果。" }),
      /* @__PURE__ */ n.jsxs("button", { className: "none-button", onClick: fe, children: [
        "仅试听口播变速 ",
        /* @__PURE__ */ n.jsx("i", { className: "bi bi-play-fill" })
      ] })
    ] }) : j && d.rate !== 1 ? /* @__PURE__ */ n.jsxs("div", { className: "speed-warning", children: [
      /* @__PURE__ */ n.jsx("b", { children: "正在允许口播随画面变速" }),
      /* @__PURE__ */ n.jsx("p", { children: "浏览器尽量保持音高，但语速仍会改变。采用后将以相同语速生成新预览。" }),
      /* @__PURE__ */ n.jsx("button", { className: "none-button", onClick: () => P({ protect: !0 }), children: "恢复口播保护" })
    ] }) : null,
    /* @__PURE__ */ n.jsxs("div", { className: "speed-result", children: [
      /* @__PURE__ */ n.jsxs("div", { children: [
        /* @__PURE__ */ n.jsx("small", { children: "这一段原本" }),
        /* @__PURE__ */ n.jsxs("strong", { children: [
          ot(M.frames),
          "秒"
        ] })
      ] }),
      /* @__PURE__ */ n.jsx("i", { className: "bi bi-arrow-right" }),
      /* @__PURE__ */ n.jsxs("div", { children: [
        /* @__PURE__ */ n.jsx("small", { children: _ ? "待试听的方案" : "变速方案" }),
        /* @__PURE__ */ n.jsxs("strong", { children: [
          ot(Zc(M, d)),
          "秒"
        ] })
      ] }),
      /* @__PURE__ */ n.jsx("span", { children: te === 0 ? "时长不变" : `${te < 0 ? "缩短" : "增加"} ${ot(Math.abs(te))}秒` })
    ] }),
    /* @__PURE__ */ n.jsxs("p", { className: "micro", children: [
      "整片预计 ",
      ot(le),
      "秒（原",
      f.toFixed(2),
      "秒，含",
      E,
      "秒品牌片尾）。帧数按24fps取整。"
    ] }),
    /* @__PURE__ */ n.jsxs("details", { className: "scope-details", children: [
      /* @__PURE__ */ n.jsx("summary", { children: "哪些内容会跟随变化？" }),
      /* @__PURE__ */ n.jsx("p", { children: "这里播放原始片段与原声，并叠加当前包装，方便判断变速效果。正式制作会让字幕、信息卡、构图、强调与图片叠加一起跟随画面变速。" }),
      /* @__PURE__ */ n.jsx("p", { children: "后续片段自动顺延或前移。背景音乐独立按新时长混音，品牌片尾保持设置中的时长；原版保留。" })
    ] }),
    V && /* @__PURE__ */ n.jsx("p", { className: "error-text", role: "alert", children: V }),
    /* @__PURE__ */ n.jsx("button", { className: "none-button", onClick: () => P({ rate: 1, protect: !0 }), children: "恢复这段原速" }),
    _ && /* @__PURE__ */ n.jsx("p", { className: "micro", children: "先试听并确认口播变速，或恢复原速，再采用方案。" })
  ] });
  return U({ preview: H, controls: h, valid: !_, contentDuration: s.reduce((x, q) => x + q.frames, 0) / 24 + te / 24 });
}
async function nv(s, d, b = fetch) {
  var z, O;
  s.set("_token", d.csrf);
  const f = await b(d.upload_url, { method: "POST", body: s, headers: { Accept: "application/json" } }), E = await f.json();
  if (!f.ok) throw Error(Object.values(E.errors || {}).flat()[0] || E.message || "音乐上传未完成");
  if (!((z = E.track) != null && z.id) || !((O = E.track) != null && O.url) || E.track.kind !== "upload") throw Error("音乐已保存，但未取得试听资料；请重新打开音乐列表。");
  return E.track;
}
const lm = ({ name: s }) => /* @__PURE__ */ n.jsx("i", { className: `bi bi-${s}`, "aria-hidden": "true" });
function uv({ tracks: s, selected: d, onSelect: b, audition: f, onListen: E, view: z, onView: O, mood: U, onMood: M }) {
  const j = ["全部风格", ...new Set(s.filter((p) => p.kind === "library").flatMap((p) => p.tags || []))], _ = s.filter((p) => z === "mine" ? p.kind === "upload" : z === "recommended" ? p.kind === "library" && p.recommended : p.kind !== "upload").filter((p) => {
    var Y;
    return z === "mine" || U === "全部风格" || ((Y = p.tags) == null ? void 0 : Y.includes(U));
  }), y = s.find((p) => p.id === d);
  return /* @__PURE__ */ n.jsxs(n.Fragment, { children: [
    /* @__PURE__ */ n.jsxs("div", { className: "music-browser-controls", children: [
      /* @__PURE__ */ n.jsx("div", { className: "preview-shortcuts", role: "group", "aria-label": "音乐列表", children: [["recommended", "推荐音乐"], ["all", "全部音乐"], ["mine", "我的上传"]].map(([p, Y]) => /* @__PURE__ */ n.jsx("button", { type: "button", className: `none-button ${z === p ? "selected" : ""}`, "aria-pressed": z === p, onClick: () => O(p), children: Y }, p)) }),
      z !== "mine" && /* @__PURE__ */ n.jsxs("label", { className: "field music-mood", children: [
        "风格",
        /* @__PURE__ */ n.jsx("select", { "aria-label": "筛选音乐风格", value: U, onChange: (p) => M(p.target.value), children: j.map((p) => /* @__PURE__ */ n.jsx("option", { children: p }, p)) })
      ] })
    ] }),
    /* @__PURE__ */ n.jsxs("p", { className: "music-current", children: [
      "当前使用：",
      y ? y.name : "不使用音乐",
      (y == null ? void 0 : y.kind) === "internal" ? " · 内部验收" : ""
    ] }),
    /* @__PURE__ */ n.jsx("div", { className: "track-list", role: "radiogroup", "aria-label": "背景音乐选择", children: _.map((p) => /* @__PURE__ */ n.jsxs("div", { className: `track-row ${d === p.id ? "selected" : ""}`, children: [
      /* @__PURE__ */ n.jsx("button", { className: "track-play", "aria-label": `${f === p.id ? "暂停" : "试听"}${p.name}`, onClick: () => E(p.id), children: /* @__PURE__ */ n.jsx(lm, { name: f === p.id ? "pause-fill" : "play-fill" }) }),
      /* @__PURE__ */ n.jsxs("button", { className: "track-select", role: "radio", "aria-checked": d === p.id, onClick: () => b(p.id), children: [
        /* @__PURE__ */ n.jsxs("span", { children: [
          /* @__PURE__ */ n.jsx("b", { children: p.name }),
          p.recommended && /* @__PURE__ */ n.jsx("em", { children: "推荐" })
        ] }),
        /* @__PURE__ */ n.jsxs("small", { children: [
          p.mood,
          p.author ? " · " + p.author : ""
        ] })
      ] }),
      /* @__PURE__ */ n.jsxs("div", { className: "track-meta", children: [
        /* @__PURE__ */ n.jsxs("small", { children: [
          Math.round(p.duration),
          "s · ",
          p.kind === "library" ? "免费" : p.kind === "internal" ? "内部验收" : "我的上传"
        ] }),
        /* @__PURE__ */ n.jsx(lm, { name: d === p.id ? "check-circle-fill" : "circle" })
      ] })
    ] }, p.id)) }),
    !_.length && /* @__PURE__ */ n.jsx("p", { className: "demo-disclosure", children: z === "mine" ? "还没有上传音乐，在下面选一个音频文件即可添加。" : "此风格暂无曲目，可以切换全部风格或上传自己的音乐。" }),
    (y == null ? void 0 : y.kind) === "library" && /* @__PURE__ */ n.jsxs("details", { className: "source-details", children: [
      /* @__PURE__ */ n.jsx("summary", { children: "当前音乐的来源与许可" }),
      /* @__PURE__ */ n.jsxs("p", { children: [
        y.author,
        " · ",
        y.license,
        " · ",
        /* @__PURE__ */ n.jsx("a", { href: y.source_url, target: "_blank", rel: "noreferrer", children: "原作者发布页" }),
        " · ",
        /* @__PURE__ */ n.jsx("a", { href: y.license_url, target: "_blank", rel: "noreferrer", children: "许可说明" })
      ] })
    ] })
  ] });
}
function iv({ runtime: s, onSaved: d }) {
  const [b, f] = wc.useState(!1), [E, z] = wc.useState(""), O = async (U) => {
    U.preventDefault();
    const M = U.currentTarget;
    f(!0), z("");
    try {
      const j = await nv(new FormData(M), s);
      s.addTrack(j), d(j.id), M.reset();
    } catch (j) {
      z(j.message);
    } finally {
      f(!1);
    }
  };
  return /* @__PURE__ */ n.jsxs("details", { className: "source-details", children: [
    /* @__PURE__ */ n.jsx("summary", { children: "上传我的音乐" }),
    /* @__PURE__ */ n.jsxs("form", { onSubmit: O, children: [
      /* @__PURE__ */ n.jsxs("label", { className: "field", children: [
        "音频文件 · MP3 / WAV / M4A / OGG",
        /* @__PURE__ */ n.jsx("input", { type: "file", name: "file", accept: ".mp3,.wav,.m4a,.ogg,audio/mpeg,audio/wav,audio/mp4,audio/ogg", required: !0 })
      ] }),
      /* @__PURE__ */ n.jsx("p", { className: "music-current", children: "上传后即可试听和使用，仅本项目可见。请使用你有权用于本视频的音乐。" }),
      /* @__PURE__ */ n.jsxs("label", { className: "field", children: [
        "音乐名称 · 选填",
        /* @__PURE__ */ n.jsx("input", { name: "name", maxLength: "100", placeholder: "默认使用文件名" })
      ] }),
      /* @__PURE__ */ n.jsx("button", { className: "outline-button", disabled: b, type: "submit", children: b ? "正在准备音乐…" : "上传并选用" }),
      E && /* @__PURE__ */ n.jsx("p", { className: "error-text", role: "alert", children: E })
    ] })
  ] });
}
const we = ({ name: s }) => /* @__PURE__ */ n.jsx("i", { className: `bi bi-${s}`, "aria-hidden": "true" }), tu = wc.createContext(null), Vc = [{ id: "minimal", name: "简洁品牌", tag: "AI 推荐", desc: "让品牌清楚留下来", note: "适合稳重、简洁的企业介绍。" }, { id: "cta", name: "行动引导", tag: "突出询价", desc: "让观众知道下一步", note: "品牌与行动提示一起出现，方便承接咨询。" }, { id: "product", name: "产品背景", tag: "强化记忆", desc: "产品与品牌一起收尾", note: "使用现有商品画面，强化产品与品牌的关联。" }], am = (s) => `${Math.floor(s / 60)}:${String(Math.floor(s % 60)).padStart(2, "0")}`;
function om({ config: s, small: d = !1 }) {
  const b = T.useContext(tu);
  return /* @__PURE__ */ n.jsxs("div", { className: `end-card end-${s.template} ${d ? "end-small" : ""}`, "aria-label": `${Vc.find((f) => f.id === s.template).name}片尾预览`, children: [
    s.template === "product" && /* @__PURE__ */ n.jsx("img", { className: "end-product-image", src: b.product_url, alt: "现有商品参考图" }),
    /* @__PURE__ */ n.jsxs("div", { className: "end-lockup", children: [
      /* @__PURE__ */ n.jsx("div", { className: "end-logo", children: b.brand.available ? /* @__PURE__ */ n.jsx("img", { src: b.brand.url, alt: "店铺现有品牌字标" }) : /* @__PURE__ */ n.jsx("span", { children: "品牌Logo待设置" }) }),
      /* @__PURE__ */ n.jsx("strong", { children: s.company || "公司名称" }),
      s.template !== "minimal" && /* @__PURE__ */ n.jsx("span", { children: s.cta || "行动提示" })
    ] })
  ] });
}
function sv({ config: s = null, compact: d = !1, onStart: b = () => {
}, pauseSignal: f = 0, contentDuration: E = null }) {
  var h;
  const z = T.useContext(tu), O = E ?? z.content_duration, [U, M] = T.useState(!1), [j, _] = T.useState(0), [y, p] = T.useState("film"), [Y, X] = T.useState(!1), [I, ie] = T.useState(""), J = T.useRef(null), ue = T.useRef(null), V = T.useRef(0), W = T.useRef(0), te = O + (s != null && s.enabled ? s.duration : 0), le = y === "outro", P = (x, q) => {
    if (!ue.current) return;
    let L = ((s == null ? void 0 : s.volume) ?? 0) / 100;
    s != null && s.duck && !q && (L *= 0.35), s != null && s.fade && (L *= Math.min(1, x / 1, Math.max(0, (te - x) / 1.2))), ue.current.volume = Y ? 0 : Math.min(1, Math.max(0, L));
  };
  T.useEffect(() => {
    P(j, le);
  }, [s == null ? void 0 : s.volume, s == null ? void 0 : s.duck, s == null ? void 0 : s.fade, Y, j, le]), T.useEffect(() => {
    var x, q;
    (x = J.current) == null || x.pause(), (q = ue.current) == null || q.pause(), M(!1), _(0), p("film"), J.current && (J.current.currentTime = 0);
  }, [s == null ? void 0 : s.enabled, s == null ? void 0 : s.template, s == null ? void 0 : s.duration, s == null ? void 0 : s.track]), T.useEffect(() => {
    var x, q;
    (x = J.current) == null || x.pause(), (q = ue.current) == null || q.pause(), M(!1), le && (W.current = Math.max(0, j - O));
  }, [f]), T.useEffect(() => {
    if (!U || !le) return;
    V.current = performance.now();
    const x = W.current;
    let q;
    const L = (S) => {
      var ne;
      const Z = x + (S - V.current) / 1e3;
      if (_(Math.min(te, O + Z)), Z >= s.duration) {
        (ne = ue.current) == null || ne.pause(), M(!1), W.current = 0;
        return;
      }
      q = requestAnimationFrame(L);
    };
    return q = requestAnimationFrame(L), () => cancelAnimationFrame(q);
  }, [U, le, te, s == null ? void 0 : s.duration]);
  const F = (x = j, q = le) => {
    s != null && s.track && ue.current && (ue.current.currentTime = x, P(x, q), ue.current.play().catch(() => ie("音乐未能播放，请再次点击试听。")));
  }, ee = () => {
    var q, L;
    if (ie(""), U) {
      (q = J.current) == null || q.pause(), (L = ue.current) == null || L.pause(), le && (W.current = Math.max(0, j - O)), M(!1);
      return;
    }
    b();
    const x = j >= te - 0.05;
    x && (p("film"), _(0), W.current = 0, J.current.currentTime = 0), (!le || x) && J.current.play().catch(() => {
      M(!1), ie("视频未能播放，请重试。");
    }), F(x ? 0 : j, x ? !1 : le), M(!0);
  }, fe = () => {
    var x, q;
    (x = J.current) == null || x.pause(), (q = ue.current) == null || q.pause(), M(!1), p("outro"), _(O), W.current = 0;
  }, H = (x) => {
    var q;
    _(x), ue.current && (ue.current.currentTime = x), x < O ? (p("film"), J.current.currentTime = x, U && J.current.play().catch(() => M(!1))) : (J.current.pause(), p("outro"), W.current = x - O, V.current = performance.now(), M(!1), (q = ue.current) == null || q.pause());
  };
  return /* @__PURE__ */ n.jsxs("div", { className: `pack-player ${d ? "compact-player" : ""}`, children: [
    /* @__PURE__ */ n.jsxs("div", { className: "preview-label", children: [
      /* @__PURE__ */ n.jsx("span", { children: le ? "品牌片尾" : "现有剪辑版" }),
      /* @__PURE__ */ n.jsx("small", { children: "9:16" })
    ] }),
    /* @__PURE__ */ n.jsxs("div", { className: "portrait-stage", children: [
      /* @__PURE__ */ n.jsx("video", { ref: J, src: z.content_url, playsInline: !0, preload: "metadata", muted: Y, hidden: le, onTimeUpdate: () => {
        y === "film" && _(Math.min(O, J.current.currentTime));
      }, onEnded: () => {
        var x;
        s != null && s.enabled ? (p("outro"), W.current = 0, _(O)) : (M(!1), (x = ue.current) == null || x.pause(), _(O));
      }, "aria-label": "现有项目成片" }),
      le && s && /* @__PURE__ */ n.jsx(om, { config: s }),
      /* @__PURE__ */ n.jsx("button", { className: "stage-play", onClick: ee, "aria-label": U ? "暂停画面预览" : "播放画面预览", children: /* @__PURE__ */ n.jsx(we, { name: U ? "pause-fill" : "play-fill" }) })
    ] }),
    (s == null ? void 0 : s.track) && /* @__PURE__ */ n.jsx("audio", { ref: ue, src: (h = z.tracks.find((x) => x.id === s.track)) == null ? void 0 : h.url, preload: "metadata" }),
    /* @__PURE__ */ n.jsxs("div", { className: "transport", children: [
      /* @__PURE__ */ n.jsx("button", { onClick: ee, "aria-label": U ? "暂停全片试听" : "全片试听", children: /* @__PURE__ */ n.jsx(we, { name: U ? "pause-fill" : "play-fill" }) }),
      /* @__PURE__ */ n.jsxs("span", { children: [
        am(j),
        " / ",
        am(te)
      ] }),
      /* @__PURE__ */ n.jsx("input", { "aria-label": "预览播放进度", type: "range", min: "0", max: te, step: ".05", value: j, onChange: (x) => H(Number(x.target.value)) }),
      /* @__PURE__ */ n.jsx("button", { onClick: () => X(!Y), "aria-label": Y ? "取消静音" : "静音", children: /* @__PURE__ */ n.jsx(we, { name: Y ? "volume-mute" : "volume-up" }) })
    ] }),
    d && /* @__PURE__ */ n.jsxs("div", { className: "preview-shortcuts", children: [
      /* @__PURE__ */ n.jsx("button", { onClick: () => {
        var x;
        H(0), J.current.pause(), (x = ue.current) == null || x.pause(), M(!1);
      }, children: "看原片" }),
      /* @__PURE__ */ n.jsx("button", { onClick: fe, disabled: !(s != null && s.enabled), children: "看片尾" }),
      /* @__PURE__ */ n.jsxs("span", { children: [
        te.toFixed(2),
        " 秒"
      ] })
    ] }),
    I && /* @__PURE__ */ n.jsx("p", { role: "alert", className: "error-text", children: I })
  ] });
}
function cv({ initial: s, onClose: d, onApply: b, working: f = !1, errorMessage: E = "", subtitleUI: z = null, initialTab: O = "subtitles", subtitleChanged: U = !1, informationUI: M = null, framingUI: j = null, emphasisUI: _ = null, overlayUI: y = null, speedUI: p = null, speedChanged: Y = !1 }) {
  const X = T.useContext(tu), I = (p == null ? void 0 : p.contentDuration) ?? X.content_duration, ie = X.tracks, [J, ue] = T.useState({ ...s }), [V, W] = T.useState(O), [te, le] = T.useState(null), [P, F] = T.useState(""), [ee, fe] = T.useState(0), [H, h] = T.useState("mine"), [x, q] = T.useState("全部风格"), L = T.useRef(null), S = T.useRef(null), Z = T.useRef(document.activeElement), ne = T.useRef(d);
  ne.current = d;
  const me = (o) => {
    var D, G, ae;
    (G = (D = S.current) == null ? void 0 : D.querySelector(".editor-scroll")) == null || G.scrollTo(0, 0), (ae = S.current) == null || ae.querySelectorAll("video,audio").forEach((ce) => ce.pause()), fe((ce) => ce + 1), W(o);
  }, w = (o, D) => ue((G) => ({ ...G, [o]: D }));
  T.useEffect(() => {
    var G, ae;
    const o = document.body.style.overflow;
    document.body.style.overflow = "hidden", (ae = (G = S.current) == null ? void 0 : G.querySelector("button")) == null || ae.focus();
    const D = (ce) => {
      if (ce.key === "Escape" && (ce.preventDefault(), ne.current()), ce.key === "Tab") {
        const de = [...S.current.querySelectorAll("button:not(:disabled),input:not(:disabled),select:not(:disabled),a[href]")].filter((nt) => nt.getClientRects().length), Oe = de[0], _e = de.at(-1);
        ce.shiftKey && document.activeElement === Oe ? (ce.preventDefault(), _e.focus()) : !ce.shiftKey && document.activeElement === _e && (ce.preventDefault(), Oe.focus());
      }
    };
    return window.addEventListener("keydown", D), () => {
      var ce, de;
      document.body.style.overflow = o, window.removeEventListener("keydown", D), (ce = L.current) == null || ce.pause(), (de = Z.current) == null || de.focus();
    };
  }, []), T.useEffect(() => {
    L.current && (L.current.volume = J.volume / 100);
  }, [J.volume]), T.useEffect(() => {
    var o;
    (o = L.current) == null || o.pause(), le(null);
  }, [V]), T.useEffect(() => {
    var o, D;
    (D = (o = S.current) == null ? void 0 : o.querySelector(".pack-preview")) == null || D.scrollTo(0, 0);
  }, [V]);
  const m = async (o) => {
    var D;
    if (F(""), te === o) {
      L.current.pause(), le(null);
      return;
    }
    fe((G) => G + 1), L.current.src = (D = X.tracks.find((G) => G.id === o)) == null ? void 0 : D.url, L.current.volume = J.volume / 100;
    try {
      await L.current.play(), le(o);
    } catch {
      F("音轨暂时无法播放，请再试一次。");
    }
  };
  return /* @__PURE__ */ n.jsx("div", { className: "pack-backdrop", onClick: (o) => {
    o.target === o.currentTarget && d();
  }, children: /* @__PURE__ */ n.jsxs("section", { ref: S, className: "pack-dialog", role: "dialog", "aria-modal": "true", "aria-labelledby": "pack-title", children: [
    /* @__PURE__ */ n.jsxs("header", { className: "pack-header", children: [
      /* @__PURE__ */ n.jsxs("div", { children: [
        /* @__PURE__ */ n.jsx("small", { children: "成片 · 包装设置" }),
        /* @__PURE__ */ n.jsx("h2", { id: "pack-title", children: "包装设置" }),
        /* @__PURE__ */ n.jsx("p", { children: "调整构图、字幕、信息卡、音乐与品牌收尾。" })
      ] }),
      /* @__PURE__ */ n.jsx("button", { className: "close-btn", "aria-label": "关闭包装设置", disabled: f, onClick: d, children: /* @__PURE__ */ n.jsx(we, { name: "x-lg" }) })
    ] }),
    /* @__PURE__ */ n.jsxs("div", { className: "pack-body", children: [
      /* @__PURE__ */ n.jsx("aside", { className: `pack-preview ${V === "speed" || V === "subtitles" || V === "information" || V === "framing" || V === "emphasis" || V === "overlay" ? "subtitle-preview" : ""}`, children: V === "speed" && p ? p.preview : V === "overlay" && y ? y.preview : V === "emphasis" && _ ? _.preview : V === "framing" && j ? j.preview : V === "information" && M ? M.preview : V === "subtitles" && z ? z.preview : /* @__PURE__ */ n.jsxs(n.Fragment, { children: [
        " ",
        /* @__PURE__ */ n.jsxs("div", { className: "eyebrow", children: [
          "边看边调整 ",
          /* @__PURE__ */ n.jsx("span", { children: "制作预览" })
        ] }),
        /* @__PURE__ */ n.jsx(sv, { contentDuration: I, config: J, compact: !0, pauseSignal: ee, onStart: () => {
          var o;
          (o = L.current) == null || o.pause(), le(null);
        } }),
        /* @__PURE__ */ n.jsxs("div", { className: "length-note", children: [
          /* @__PURE__ */ n.jsx(we, { name: "clock" }),
          /* @__PURE__ */ n.jsxs("div", { children: [
            "原片 ",
            /* @__PURE__ */ n.jsxs("b", { children: [
              I.toFixed(2),
              "s"
            ] }),
            J.enabled && /* @__PURE__ */ n.jsxs(n.Fragment, { children: [
              " ＋ 片尾 ",
              /* @__PURE__ */ n.jsxs("b", { children: [
                J.duration,
                "s"
              ] })
            ] }),
            /* @__PURE__ */ n.jsxs("strong", { children: [
              "预览总长 ",
              (I + (J.enabled ? J.duration : 0)).toFixed(2),
              "s"
            ] })
          ] })
        ] }),
        /* @__PURE__ */ n.jsx("p", { className: "preview-footnote", children: "试听包含原片口播。构图、字幕和信息卡修改采用后统一合成；原版保留。" })
      ] }) }),
      /* @__PURE__ */ n.jsxs("div", { className: "pack-editor", children: [
        /* @__PURE__ */ n.jsxs("div", { className: "pack-tabs", role: "tablist", "aria-label": "包装类别", children: [
          z && /* @__PURE__ */ n.jsxs("button", { role: "tab", "aria-selected": V === "subtitles", "aria-controls": "subtitles-panel", id: "subtitles-tab", onClick: () => me("subtitles"), children: [
            /* @__PURE__ */ n.jsx(we, { name: "badge-cc" }),
            " 字幕效果"
          ] }),
          M && /* @__PURE__ */ n.jsxs("button", { role: "tab", "aria-selected": V === "information", "aria-controls": "information-panel", id: "information-tab", onClick: () => me("information"), children: [
            /* @__PURE__ */ n.jsx(we, { name: "card-heading" }),
            " 信息卡"
          ] }),
          j && /* @__PURE__ */ n.jsxs("button", { role: "tab", "aria-selected": V === "framing", "aria-controls": "framing-panel", id: "framing-tab", onClick: () => me("framing"), children: [
            /* @__PURE__ */ n.jsx(we, { name: "bounding-box" }),
            " 画面构图"
          ] }),
          _ && /* @__PURE__ */ n.jsxs("button", { role: "tab", "aria-selected": V === "emphasis", onClick: () => me("emphasis"), children: [
            /* @__PURE__ */ n.jsx(we, { name: "bullseye" }),
            " 内容强调"
          ] }),
          y && /* @__PURE__ */ n.jsxs("button", { role: "tab", "aria-selected": V === "overlay", onClick: () => me("overlay"), children: [
            /* @__PURE__ */ n.jsx(we, { name: "images" }),
            " 多层排版"
          ] }),
          /* @__PURE__ */ n.jsxs("button", { role: "tab", "aria-selected": V === "speed", onClick: () => me("speed"), children: [
            /* @__PURE__ */ n.jsx(we, { name: "speedometer2" }),
            " 节奏与衔接"
          ] }),
          /* @__PURE__ */ n.jsxs("button", { role: "tab", "aria-selected": V === "brand", "aria-controls": "brand-panel", id: "brand-tab", onClick: () => me("brand"), children: [
            /* @__PURE__ */ n.jsx(we, { name: "badge-ad" }),
            " 品牌片尾"
          ] }),
          /* @__PURE__ */ n.jsxs("button", { role: "tab", "aria-selected": V === "music", "aria-controls": "music-panel", id: "music-tab", onClick: () => me("music"), children: [
            /* @__PURE__ */ n.jsx(we, { name: "music-note-beamed" }),
            " 背景音乐"
          ] })
        ] }),
        /* @__PURE__ */ n.jsx("div", { className: "editor-scroll", children: V === "speed" && p ? /* @__PURE__ */ n.jsx("div", { role: "tabpanel", "aria-label": "节奏与衔接", children: p.controls }) : V === "overlay" && y ? /* @__PURE__ */ n.jsx("div", { role: "tabpanel", "aria-label": "多层排版", children: y.controls }) : V === "emphasis" && _ ? /* @__PURE__ */ n.jsx("div", { role: "tabpanel", "aria-label": "内容强调", children: _.controls }) : V === "framing" && j ? /* @__PURE__ */ n.jsx("div", { id: "framing-panel", role: "tabpanel", "aria-labelledby": "framing-tab", children: j.controls }) : V === "information" && M ? /* @__PURE__ */ n.jsx("div", { id: "information-panel", role: "tabpanel", "aria-labelledby": "information-tab", children: M.controls }) : V === "subtitles" && z ? /* @__PURE__ */ n.jsx("div", { id: "subtitles-panel", role: "tabpanel", "aria-labelledby": "subtitles-tab", children: z.controls }) : V === "brand" ? /* @__PURE__ */ n.jsxs("div", { id: "brand-panel", role: "tabpanel", "aria-labelledby": "brand-tab", children: [
          /* @__PURE__ */ n.jsxs("div", { className: "section-top", children: [
            /* @__PURE__ */ n.jsxs("div", { children: [
              /* @__PURE__ */ n.jsx("h3", { children: "选一个适合品牌的收尾" }),
              /* @__PURE__ */ n.jsx("p", { children: "模板保证排版，内容和时长由你决定。" })
            ] }),
            /* @__PURE__ */ n.jsxs("label", { className: "switch-label", children: [
              /* @__PURE__ */ n.jsx("input", { type: "checkbox", disabled: !X.brand.available, checked: J.enabled, onChange: (o) => w("enabled", o.target.checked) }),
              /* @__PURE__ */ n.jsx("span", { children: "使用片尾" })
            ] })
          ] }),
          /* @__PURE__ */ n.jsx("div", { className: `template-grid ${J.enabled ? "" : "is-disabled"}`, role: "radiogroup", "aria-label": "品牌片尾模板", children: Vc.map((o) => /* @__PURE__ */ n.jsxs("button", { role: "radio", "aria-checked": J.template === o.id, disabled: !J.enabled || o.id === "product" && !X.product_url, className: `template-option ${J.template === o.id ? "selected" : ""}`, onClick: () => w("template", o.id), children: [
            /* @__PURE__ */ n.jsx("div", { className: "template-thumbnail", children: /* @__PURE__ */ n.jsx(om, { config: { ...J, template: o.id }, small: !0 }) }),
            /* @__PURE__ */ n.jsxs("div", { className: "template-name", children: [
              /* @__PURE__ */ n.jsx("b", { children: o.name }),
              J.template === o.id && /* @__PURE__ */ n.jsx(we, { name: "check-circle-fill" })
            ] }),
            /* @__PURE__ */ n.jsx("small", { children: o.desc }),
            o.id === "minimal" && /* @__PURE__ */ n.jsx("span", { className: "recommend", children: "AI 推荐" })
          ] }, o.id)) }),
          /* @__PURE__ */ n.jsxs("div", { className: "recommendation", children: [
            /* @__PURE__ */ n.jsx(we, { name: "stars" }),
            /* @__PURE__ */ n.jsxs("p", { children: [
              /* @__PURE__ */ n.jsx("b", { children: "模板建议" }),
              Vc.find((o) => o.id === J.template).note,
              " 品牌片尾独立追加，保留原片口播与结尾。"
            ] })
          ] }),
          /* @__PURE__ */ n.jsxs("fieldset", { disabled: !J.enabled, children: [
            /* @__PURE__ */ n.jsxs("div", { className: "brand-source", children: [
              X.brand.available ? /* @__PURE__ */ n.jsx("img", { src: X.brand.url, alt: "现有店铺字标" }) : /* @__PURE__ */ n.jsx(we, { name: "image" }),
              /* @__PURE__ */ n.jsxs("div", { children: [
                /* @__PURE__ */ n.jsx("b", { children: "现有店铺字标" }),
                /* @__PURE__ */ n.jsx("small", { children: "采用即确认使用此Logo与公司名称" })
              ] }),
              /* @__PURE__ */ n.jsx(we, { name: "image" })
            ] }),
            /* @__PURE__ */ n.jsxs("label", { className: "field", children: [
              "公司名称",
              /* @__PURE__ */ n.jsx("input", { maxLength: "70", value: J.company, onChange: (o) => w("company", o.target.value), "aria-label": "片尾公司名称" })
            ] }),
            J.template !== "minimal" && /* @__PURE__ */ n.jsxs("label", { className: "field", children: [
              "行动提示",
              /* @__PURE__ */ n.jsx("input", { maxLength: "45", "aria-label": "片尾行动提示", value: J.cta, onChange: (o) => w("cta", o.target.value) })
            ] }),
            /* @__PURE__ */ n.jsxs("div", { className: "field-row", children: [
              /* @__PURE__ */ n.jsxs("label", { className: "field", children: [
                "展示时长",
                /* @__PURE__ */ n.jsxs("select", { value: J.duration, onChange: (o) => w("duration", Number(o.target.value)), children: [
                  /* @__PURE__ */ n.jsx("option", { value: "2", children: "2 秒 · 简短" }),
                  /* @__PURE__ */ n.jsx("option", { value: "3", children: "3 秒 · 推荐" }),
                  /* @__PURE__ */ n.jsx("option", { value: "4", children: "4 秒 · 从容" }),
                  /* @__PURE__ */ n.jsx("option", { value: "5", children: "5 秒 · 更多阅读时间" })
                ] })
              ] }),
              /* @__PURE__ */ n.jsxs("div", { className: "motion-note", children: [
                /* @__PURE__ */ n.jsx(we, { name: "film" }),
                /* @__PURE__ */ n.jsxs("span", { children: [
                  "轻柔淡入，停稳展示",
                  /* @__PURE__ */ n.jsx("br", {}),
                  /* @__PURE__ */ n.jsx("small", { children: "不缩短原片来补时" })
                ] })
              ] })
            ] })
          ] })
        ] }) : /* @__PURE__ */ n.jsxs("div", { id: "music-panel", role: "tabpanel", "aria-labelledby": "music-tab", children: [
          /* @__PURE__ */ n.jsxs("div", { className: "section-top", children: [
            /* @__PURE__ */ n.jsxs("div", { children: [
              /* @__PURE__ */ n.jsx("h3", { children: "让音乐衬托口播" }),
              /* @__PURE__ */ n.jsx("p", { children: "先试听风格，再随全片听听是否合适。" })
            ] }),
            /* @__PURE__ */ n.jsx("button", { className: `none-button ${J.track ? "" : "selected"}`, onClick: () => {
              var o;
              w("track", null), (o = L.current) == null || o.pause(), le(null);
            }, children: "不使用音乐" })
          ] }),
          /* @__PURE__ */ n.jsxs("div", { className: "demo-disclosure", children: [
            /* @__PURE__ */ n.jsx(we, { name: "info-circle" }),
            " 内置音乐免费用于视频制作；也可以上传自己的音乐。"
          ] }),
          /* @__PURE__ */ n.jsx(uv, { tracks: ie, selected: J.track, onSelect: (o) => w("track", o), audition: te, onListen: m, view: H, onView: (o) => {
            var D;
            (D = L.current) == null || D.pause(), le(null), h(o);
          }, mood: x, onMood: q }),
          /* @__PURE__ */ n.jsx("audio", { ref: L, onEnded: () => le(null) }),
          P && /* @__PURE__ */ n.jsx("p", { role: "alert", className: "error-text", children: P }),
          /* @__PURE__ */ n.jsxs("fieldset", { disabled: !J.track, children: [
            /* @__PURE__ */ n.jsxs("div", { className: "volume-control", children: [
              /* @__PURE__ */ n.jsxs("label", { htmlFor: "music-volume", children: [
                /* @__PURE__ */ n.jsx("b", { children: "音乐音量" }),
                /* @__PURE__ */ n.jsxs("span", { children: [
                  J.volume,
                  "%"
                ] })
              ] }),
              /* @__PURE__ */ n.jsxs("div", { children: [
                /* @__PURE__ */ n.jsx(we, { name: "volume-down" }),
                /* @__PURE__ */ n.jsx("input", { id: "music-volume", type: "range", min: "0", max: "100", step: "1", value: J.volume, onChange: (o) => w("volume", Number(o.target.value)) }),
                /* @__PURE__ */ n.jsx(we, { name: "volume-up" })
              ] }),
              /* @__PURE__ */ n.jsx("small", { children: "轻轻铺在声音后面，先让口播听得清楚。" })
            ] }),
            /* @__PURE__ */ n.jsxs("label", { className: "audio-toggle", children: [
              /* @__PURE__ */ n.jsxs("span", { children: [
                /* @__PURE__ */ n.jsx("b", { children: "口播时自动压低音乐" }),
                /* @__PURE__ */ n.jsx("small", { children: "生成后可试听实际混音效果" })
              ] }),
              /* @__PURE__ */ n.jsx("input", { type: "checkbox", checked: J.duck, onChange: (o) => w("duck", o.target.checked) })
            ] }),
            /* @__PURE__ */ n.jsxs("label", { className: "audio-toggle", children: [
              /* @__PURE__ */ n.jsxs("span", { children: [
                /* @__PURE__ */ n.jsx("b", { children: "开头淡入，结尾淡出" }),
                /* @__PURE__ */ n.jsx("small", { children: "让音乐自然进入与结束" })
              ] }),
              /* @__PURE__ */ n.jsx("input", { type: "checkbox", checked: J.fade, onChange: (o) => w("fade", o.target.checked) })
            ] })
          ] }),
          /* @__PURE__ */ n.jsx(iv, {}),
          /* @__PURE__ */ n.jsxs("details", { className: "source-details", children: [
            /* @__PURE__ */ n.jsx("summary", { children: "音乐来源与使用范围" }),
            /* @__PURE__ */ n.jsxs("p", { children: [
              "内置音乐已保存作者与CC0许可记录；我的上传仅本项目可用。来源和备注可以稍后补充。短音轨结束后静音，不自动循环。免费来源可参考 ",
              /* @__PURE__ */ n.jsx("a", { href: "https://mixkit.co/free-stock-music/", target: "_blank", rel: "noreferrer", children: "Mixkit" }),
              "、",
              /* @__PURE__ */ n.jsx("a", { href: "https://pixabay.com/music/", target: "_blank", rel: "noreferrer", children: "Pixabay" }),
              "，逐曲确认使用许可，不直接作为公共下载库。"
            ] })
          ] })
        ] }) })
      ] })
    ] }),
    E && /* @__PURE__ */ n.jsx("p", { role: "alert", className: "error-text", style: { padding: "0 28px" }, children: E }),
    /* @__PURE__ */ n.jsxs("footer", { className: "pack-footer", children: [
      /* @__PURE__ */ n.jsxs("div", { children: [
        /* @__PURE__ */ n.jsx(we, { name: "shield-check" }),
        /* @__PURE__ */ n.jsxs("span", { children: [
          "本地合成 · 保留原版",
          /* @__PURE__ */ n.jsx("br", {}),
          /* @__PURE__ */ n.jsx("small", { children: "生成后再验收效果" })
        ] })
      ] }),
      /* @__PURE__ */ n.jsxs("div", { className: "footer-actions", children: [
        /* @__PURE__ */ n.jsx("button", { className: "plain-button", disabled: f, onClick: d, children: "取消" }),
        /* @__PURE__ */ n.jsxs("button", { className: "primary-button", disabled: f || J.enabled && !J.company.trim() || U && !(z != null && z.ready) || M && !M.valid || j && !j.valid || _ && !_.valid || y && !y.valid || Y && !(p != null && p.valid), onClick: () => {
          var o;
          (o = L.current) == null || o.pause(), b({ ...J });
        }, children: [
          f ? "正在保存制作任务…" : "采用设置，生成新预览",
          " ",
          /* @__PURE__ */ n.jsx(we, { name: "arrow-right" })
        ] })
      ] })
    ] })
  ] }) });
}
function _i({ card: s, cards: d, frames: b, onChange: f, label: E = "拖动调整信息卡出现区间" }) {
  const z = T.useRef(null), O = d.filter((p) => p.id !== s.id && p.scene_number === s.scene_number), U = Math.max(0, ...O.filter((p) => p.end_frame <= s.start_frame).map((p) => p.end_frame)), M = Math.min(b, ...O.filter((p) => p.start_frame >= s.end_frame).map((p) => p.start_frame)), j = (p) => p === "start_frame" ? [U, s.end_frame - 36] : [s.start_frame + 36, M], _ = (p, Y) => {
    const [X, I] = j(p);
    X <= I && f(p, Math.max(X, Math.min(I, Math.round(Y))));
  }, y = (p, Y) => {
    const X = z.current.getBoundingClientRect();
    _(Y, (p.clientX - X.left) / X.width * b);
  };
  return /* @__PURE__ */ n.jsxs(n.Fragment, { children: [
    /* @__PURE__ */ n.jsxs("div", { className: "info-range", ref: z, role: "group", "aria-label": E, children: [
      /* @__PURE__ */ n.jsx("div", { className: "info-range-rail" }),
      /* @__PURE__ */ n.jsx("div", { className: "info-range-fill", style: { left: `${s.start_frame / b * 100}%`, width: `${(s.end_frame - s.start_frame) / b * 100}%` } }),
      [["start_frame", "开始"], ["end_frame", "结束"]].map(([p, Y]) => /* @__PURE__ */ n.jsx("button", { type: "button", className: "info-range-handle", role: "slider", "aria-label": `拖动${Y}时间`, "aria-valuemin": j(p)[0], "aria-valuemax": j(p)[1], "aria-valuenow": s[p], "aria-valuetext": `${(s[p] / 24).toFixed(2)}秒`, style: { left: `${s[p] / b * 100}%` }, onPointerDown: (X) => {
        X.preventDefault(), X.currentTarget.setPointerCapture(X.pointerId), y(X, p);
      }, onPointerMove: (X) => {
        X.currentTarget.hasPointerCapture(X.pointerId) && y(X, p);
      }, onPointerUp: (X) => {
        X.currentTarget.hasPointerCapture(X.pointerId) && X.currentTarget.releasePointerCapture(X.pointerId);
      }, onKeyDown: (X) => {
        const I = { ArrowLeft: -1, ArrowDown: -1, ArrowRight: 1, ArrowUp: 1, PageDown: -24, PageUp: 24 }[X.key];
        I !== void 0 ? (X.preventDefault(), _(p, s[p] + I)) : (X.key === "Home" || X.key === "End") && (X.preventDefault(), _(p, j(p)[X.key === "Home" ? 0 : 1]));
      }, children: /* @__PURE__ */ n.jsx("span", {}) }, p))
    ] }),
    /* @__PURE__ */ n.jsx("div", { className: "info-range-help", children: "拖左端调整开始，拖右端调整结束 · 最短1.50秒" })
  ] });
}
const nm = { enabled: !0, scene_number: 2, url: "/assets/product.png", name: "现有产品参考图", ratio: 1.5, x: 75, y: 27, width: 36, radius: 16, border: 3, color: "#ffffff", start_frame: 24, end_frame: 96 }, It = (s) => (s / 24).toFixed(2);
function rv({ scene: s, draft: d, onChange: b, layers: f, selectedId: E, onSelect: z, onLayerChange: O, frame: U, setFrame: M, epoch: j, compare: _, onError: y, visual: p }) {
  const Y = T.useRef(), X = T.useRef(), [I, ie] = T.useState(!1), [J, ue] = T.useState(0.25), [V, W] = T.useState(!1), te = _ ? s.start_frame : s.in_frame, le = (h) => {
    var q;
    const x = Math.max(0, Math.min(s.frames - 1, h));
    M(x), ((q = Y.current) == null ? void 0 : q.readyState) >= 1 && (Y.current.currentTime = (te + x) / 24);
  };
  T.useEffect(() => {
    const h = new ResizeObserver((x) => ue(x[0].contentRect.width / 720));
    return h.observe(X.current), () => h.disconnect();
  }, []), T.useEffect(() => {
    Y.current.pause(), le(U);
  }, [s.number, j, _]), T.useEffect(() => {
    const h = Y.current;
    let x;
    const q = () => {
      let Z = Math.round(h.currentTime * 24) - te;
      Z >= s.frames && (h.pause(), Z = s.frames - 1), M(Math.max(0, Z)), h.paused || (x = requestAnimationFrame(q));
    }, L = () => {
      ie(!0), x = requestAnimationFrame(q);
    }, S = () => ie(!1);
    return h.addEventListener("play", L), h.addEventListener("pause", S), () => {
      cancelAnimationFrame(x), h.pause(), h.removeEventListener("play", L), h.removeEventListener("pause", S);
    };
  }, [s.number, _]);
  const P = ha(_ ? null : p.framing.find((h) => h.scene_number === s.number), U, s.frames), F = p.cards.find((h) => h.scene_number === s.number && U >= h.start_frame && U < h.end_frame), ee = p.recipes.find((h) => h.scene.number === s.number), fe = F != null && F.replace_product_info ? { ...s, original_subtitle: s.spoken_text } : s;
  d.enabled && d.scene_number === s.number && U >= d.start_frame && U < d.end_frame;
  const H = (h, x, q) => {
    const L = X.current.getBoundingClientRect(), S = x.width * 720 / x.ratio / 1280;
    q({ x: Math.round(Math.max(x.width / 2, Math.min(100 - x.width / 2, (h.clientX - L.left) / L.width * 100))), y: Math.round(Math.max(S / 2, Math.min(100 - S / 2, (h.clientY - L.top) / L.height * 100))) });
  };
  return /* @__PURE__ */ n.jsxs(n.Fragment, { children: [
    /* @__PURE__ */ n.jsxs("div", { className: "pack-player compact-player", children: [
      /* @__PURE__ */ n.jsxs("div", { className: "portrait-stage overlay-stage", ref: X, children: [
        /* @__PURE__ */ n.jsx("video", { ref: Y, src: _ ? p.comparisonUrl : s.raw_url, style: { transformOrigin: "0 0", transform: `translate(${P.x * 100}%,${P.y * 100}%) scale(${P.scale})` }, muted: V, playsInline: !0, preload: "auto", onLoadedMetadata: () => le(U), onError: () => y("现有片段未能读取，请刷新样本。") }),
        !_ && /* @__PURE__ */ n.jsxs(n.Fragment, { children: [
          /* @__PURE__ */ n.jsx(an, { cards: p.cards, overlays: p.overlays, scene: s, framing: p.framing.find((h) => h.scene_number === s.number), effect: p.emphasis.find((h) => h.scene_number === s.number), frame: U }),
          /* @__PURE__ */ n.jsxs("div", { style: { position: "absolute", left: 0, top: 0, width: 720, height: 1280, transform: `scale(${J})`, transformOrigin: "top left", pointerEvents: "none" }, children: [
            /* @__PURE__ */ n.jsx(tn, { scene: fe, settings: (ee == null ? void 0 : ee.settings) || p.settings, original: (ee == null ? void 0 : ee.original) || !1, frame: U }),
            /* @__PURE__ */ n.jsx(ln, { card: F })
          ] })
        ] }),
        !_ && f.filter((h) => h.enabled && h.url && h.scene_number === s.number && U >= h.start_frame && U < h.end_frame).map((h) => {
          const x = f.findIndex((L) => L.id === h.id), q = (L) => O(h.id, L);
          return /* @__PURE__ */ n.jsxs("div", { className: `overlay-image ${h.id === E ? "is-selected" : ""}`, style: { left: `${h.x}%`, top: `${h.y}%`, width: `${h.width}%`, borderRadius: h.radius * J, border: `${h.border * J}px solid ${h.color}` }, children: [
            /* @__PURE__ */ n.jsx("img", { src: h.url, alt: h.name, draggable: "false", onLoad: (L) => {
              const S = L.currentTarget.naturalWidth / L.currentTarget.naturalHeight;
              Math.abs(S - h.ratio) > 0.01 && q({ ratio: S });
            } }),
            /* @__PURE__ */ n.jsx("button", { className: "overlay-move", "aria-label": `选择并拖动图片${x + 1}`, onPointerDown: (L) => {
              L.preventDefault(), z(h.id), Y.current.pause(), L.currentTarget.setPointerCapture(L.pointerId);
            }, onPointerMove: (L) => {
              L.currentTarget.hasPointerCapture(L.pointerId) && H(L, h, q);
            }, onPointerUp: (L) => L.currentTarget.releasePointerCapture(L.pointerId), onKeyDown: (L) => {
              const S = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] }[L.key];
              S && (L.preventDefault(), q({ x: Math.max(h.width / 2, Math.min(100 - h.width / 2, h.x + S[0])), y: Math.max(5, Math.min(95, h.y + S[1])) }));
            }, children: /* @__PURE__ */ n.jsxs("span", { children: [
              x + 1,
              " · ",
              h.id === E ? "拖动" : "点击选择"
            ] }) }),
            h.id === E && /* @__PURE__ */ n.jsx("button", { className: "overlay-resize", "aria-label": "拖动缩放叠加图片", onPointerDown: (L) => {
              L.preventDefault(), z(h.id), Y.current.pause(), L.currentTarget.setPointerCapture(L.pointerId);
            }, onPointerMove: (L) => {
              if (L.currentTarget.hasPointerCapture(L.pointerId)) {
                const S = X.current.getBoundingClientRect();
                q({ width: Math.round(Math.max(15, Math.min(55, (L.clientX - S.left - S.width * h.x / 100) / S.width * 200))) });
              }
            }, onPointerUp: (L) => L.currentTarget.releasePointerCapture(L.pointerId), onKeyDown: (L) => {
              (L.key === "ArrowLeft" || L.key === "ArrowRight") && (L.preventDefault(), q({ width: Math.max(15, Math.min(55, h.width + (L.key === "ArrowRight" ? 1 : -1))) }));
            }, children: /* @__PURE__ */ n.jsx("i", { className: "bi bi-arrows-angle-expand" }) })
          ] }, h.id);
        })
      ] }),
      /* @__PURE__ */ n.jsxs("div", { className: "transport", children: [
        /* @__PURE__ */ n.jsx("button", { "aria-label": I ? "暂停排版预览" : "播放排版预览", onClick: () => {
          I ? Y.current.pause() : (U >= s.frames - 1 && le(0), Y.current.play().catch(() => y("请再点击播放。")));
        }, children: /* @__PURE__ */ n.jsx("i", { className: `bi bi-${I ? "pause-fill" : "play-fill"}` }) }),
        /* @__PURE__ */ n.jsxs("span", { children: [
          (U / 24).toFixed(1),
          " / ",
          (s.frames / 24).toFixed(1)
        ] }),
        /* @__PURE__ */ n.jsx("input", { "aria-label": "排版预览播放进度", type: "range", min: "0", max: s.frames - 1, value: U, onChange: (h) => le(+h.target.value) }),
        /* @__PURE__ */ n.jsx("button", { "aria-label": V ? "开启排版预览声音" : "静音排版预览", onClick: () => W(!V), children: /* @__PURE__ */ n.jsx("i", { className: `bi bi-volume-${V ? "mute" : "up"}` }) })
      ] })
    ] }),
    /* @__PURE__ */ n.jsx("span", { className: "info-live-state", children: _ ? "现有成片对比" : f.some((h) => h.enabled && h.url && h.scene_number === s.number && U >= h.start_frame && U < h.end_frame) ? "叠加图片显示中" : "当前时刻无叠加图片" })
  ] });
}
function fv({ rows: s, draft: d, setDraft: b, children: f, visual: E, onTouch: z = () => {
} }) {
  var ne, me;
  const O = T.useContext(tu), [U, M] = T.useState(!1), j = O.overlay_images || [], _ = d.layers || [], y = _.find((w) => w.id === d.selected_id) || _[0] || { ...nm, id: "empty", scene_number: s[Math.min(1, s.length - 1)].number, start_frame: 0, end_frame: s[Math.min(1, s.length - 1)].frames }, p = y.id, Y = (w) => b((m) => ({ ...m, selected_id: w })), X = (w, m) => {
    z(), b((o) => ({ ...o, layers: o.layers.map((D) => {
      if (D.id !== w) return D;
      const G = { ...D, ...m };
      G.width = Math.min(G.width, Math.max(15, Math.min(55, 90 * G.ratio * 1280 / 720)));
      const ae = G.width * 720 / G.ratio / 1280;
      return G.x = Math.max(G.width / 2, Math.min(100 - G.width / 2, G.x)), G.y = Math.max(ae / 2, Math.min(100 - ae / 2, G.y)), G;
    }) })), ee(!1);
  }, I = () => {
    if (_.length >= 3) return;
    z();
    const w = crypto.randomUUID();
    b((m) => {
      var o, D, G;
      return { ...m, selected_id: w, layers: [...m.layers, { ...nm, image_id: ((o = j[0]) == null ? void 0 : o.id) || "", url: ((D = j[0]) == null ? void 0 : D.url) || "", name: ((G = j[0]) == null ? void 0 : G.name) || "请选择图片", scene_number: y.scene_number, id: w, x: _.length === 1 ? 72 : 50, y: _.length === 1 ? 43 : 62, start_frame: y.start_frame, end_frame: y.end_frame }] };
    }), ee(!1), x(y.start_frame + 12);
  }, ie = (w) => {
    z(), b((m) => {
      var D;
      const o = m.layers.filter((G) => G.id !== w);
      return { ...m, layers: o, selected_id: m.selected_id === w ? (D = o.at(-1)) == null ? void 0 : D.id : m.selected_id };
    });
  }, J = (w) => {
    z();
    const m = _.filter((o) => o.scene_number === y.scene_number).map((o) => o.id);
    b((o) => ({ ...o, layers: o.layers.map((D) => {
      const G = m.indexOf(D.id);
      return G < 0 ? D : { ...D, x: w ? 76 : (G + 1) * 100 / (m.length + 1), y: w ? 22 + G * 21 : 38, width: w ? 28 : Math.round(Math.min(35, 70 / m.length)) };
    }) })), ee(!1), x(y.start_frame + 12);
  }, ue = (w) => {
    z(), b((m) => {
      const o = [...m.layers], D = o.findIndex((ae) => ae.id === p), G = D + w;
      return G < 0 || G >= o.length ? m : ([o[D], o[G]] = [o[G], o[D]], { ...m, layers: o });
    });
  }, V = s.find((w) => w.number === y.scene_number) || s[0], [W, te] = T.useState(48), [le, P] = T.useState(0), [F, ee] = T.useState(!1), [fe, H] = T.useState("");
  T.useRef([]);
  const h = (w) => X(p, w), x = (w) => {
    te(Math.max(0, Math.min(V.frames - 1, w))), P((m) => m + 1);
  }, q = (w, m) => {
    h({ [w]: m }), x(w === "start_frame" ? m : m - 1);
  }, L = async (w) => {
    var o;
    const m = (o = w.target.files) == null ? void 0 : o[0];
    if (!(!m || U)) {
      M(!0), H("");
      try {
        const D = new FormData();
        D.append("file", m);
        const G = await fetch(O.overlay_upload_url, { method: "POST", headers: { "X-CSRF-TOKEN": O.csrf, Accept: "application/json" }, body: D }), ae = await G.json();
        if (!G.ok) throw Error(ae.message || "图片上传失败");
        O.addOverlayImage(ae.image), h({ image_id: ae.image.id, url: ae.image.url, name: ae.image.name, ratio: ae.image.ratio }), x(y.start_frame + 12);
      } catch (D) {
        H(D.message);
      } finally {
        M(!1);
      }
    }
  }, S = /* @__PURE__ */ n.jsxs(n.Fragment, { children: [
    /* @__PURE__ */ n.jsxs("div", { className: "eyebrow", children: [
      "边看边调整 ",
      /* @__PURE__ */ n.jsx("span", { children: "当前片段预览" })
    ] }),
    /* @__PURE__ */ n.jsx(rv, { scene: V, draft: y, onChange: h, layers: _, selectedId: p, onSelect: Y, onLayerChange: X, frame: W, setFrame: te, epoch: le, compare: F, onError: H, visual: E }),
    /* @__PURE__ */ n.jsxs("div", { className: "preview-shortcuts", children: [
      /* @__PURE__ */ n.jsx("button", { className: "none-button", onClick: () => ee(!F), children: F ? "看叠加效果" : "对比现有成片" }),
      /* @__PURE__ */ n.jsx("button", { className: "none-button", onClick: () => x(y.start_frame), children: "重播区间" })
    ] }),
    /* @__PURE__ */ n.jsxs("div", { className: "length-note", children: [
      /* @__PURE__ */ n.jsx("i", { className: "bi bi-clock" }),
      /* @__PURE__ */ n.jsxs("div", { children: [
        "第",
        V.number,
        "段 · ",
        It(V.start_frame),
        "–",
        It(V.end_frame),
        "秒",
        /* @__PURE__ */ n.jsx("strong", { children: "整片时长不变" })
      ] })
    ] }),
    /* @__PURE__ */ n.jsx("p", { className: "preview-footnote", children: "片段预览为原声；构图、字幕、信息卡和强调独立保留。音乐与片尾在整片合成时保留。请播放确认图片遮挡。" })
  ] }), Z = /* @__PURE__ */ n.jsxs(n.Fragment, { children: [
    /* @__PURE__ */ n.jsxs("div", { className: "section-top", children: [
      /* @__PURE__ */ n.jsxs("div", { children: [
        /* @__PURE__ */ n.jsx("h3", { children: "把想展示的图片，放在一起" }),
        /* @__PURE__ */ n.jsx("p", { children: "最多3张图片，可同时出现，也可先后展示。" })
      ] }),
      /* @__PURE__ */ n.jsxs("button", { className: "none-button overlay-add", disabled: _.length >= 3, onClick: I, children: [
        /* @__PURE__ */ n.jsx("i", { className: "bi bi-plus-lg" }),
        " 添加图片"
      ] })
    ] }),
    /* @__PURE__ */ n.jsxs("div", { className: "overlay-layer-heading", children: [
      /* @__PURE__ */ n.jsxs("b", { children: [
        "图片列表 ",
        /* @__PURE__ */ n.jsxs("span", { children: [
          _.length,
          "/3"
        ] })
      ] }),
      /* @__PURE__ */ n.jsx("small", { children: "点选一张，再调整它" })
    ] }),
    /* @__PURE__ */ n.jsx("div", { className: "overlay-layer-list", children: _.map((w, m) => /* @__PURE__ */ n.jsxs("div", { className: `overlay-layer-row ${w.id === p ? "selected" : ""}`, children: [
      /* @__PURE__ */ n.jsxs("button", { className: "overlay-select", "aria-label": `编辑图片${m + 1}`, "aria-pressed": w.id === p, onClick: () => {
        Y(w.id), x(w.start_frame + 12);
      }, children: [
        w.url ? /* @__PURE__ */ n.jsx("img", { src: w.url, alt: "" }) : /* @__PURE__ */ n.jsx("i", { className: "bi bi-image" }),
        /* @__PURE__ */ n.jsxs("div", { children: [
          /* @__PURE__ */ n.jsxs("b", { children: [
            "图片 ",
            m + 1
          ] }),
          /* @__PURE__ */ n.jsx("small", { children: w.enabled ? `第${w.scene_number}段 · ${It(w.start_frame)}–${It(w.end_frame)}秒` : "已隐藏" })
        ] })
      ] }),
      /* @__PURE__ */ n.jsx("button", { className: "none-button overlay-icon", "aria-label": `${w.enabled ? "隐藏" : "显示"}图片${m + 1}`, onClick: () => X(w.id, { enabled: !w.enabled }), children: /* @__PURE__ */ n.jsx("i", { className: `bi bi-eye${w.enabled ? "" : "-slash"}` }) }),
      /* @__PURE__ */ n.jsx("button", { className: "none-button overlay-icon", "aria-label": `删除图片${m + 1}`, onClick: () => ie(w.id), children: /* @__PURE__ */ n.jsx("i", { className: "bi bi-trash3" }) })
    ] }, w.id)) }),
    /* @__PURE__ */ n.jsxs("div", { className: "overlay-arrange", children: [
      /* @__PURE__ */ n.jsx("span", { children: "快速排好" }),
      /* @__PURE__ */ n.jsxs("button", { className: "none-button", disabled: _.length < 2, onClick: () => J(!1), children: [
        /* @__PURE__ */ n.jsx("i", { className: "bi bi-layout-three-columns" }),
        " 横向排列"
      ] }),
      /* @__PURE__ */ n.jsxs("button", { className: "none-button", disabled: _.length < 2, onClick: () => J(!0), children: [
        /* @__PURE__ */ n.jsx("i", { className: "bi bi-layout-text-sidebar" }),
        " 纵向排列"
      ] })
    ] }),
    /* @__PURE__ */ n.jsxs("div", { className: "overlay-edit-title", children: [
      /* @__PURE__ */ n.jsxs("b", { children: [
        "正在编辑 · 图片 ",
        _.findIndex((w) => w.id === p) + 1
      ] }),
      /* @__PURE__ */ n.jsx("span", { children: "仅修改这一张" })
    ] }),
    /* @__PURE__ */ n.jsxs("div", { className: "overlay-source", children: [
      y.url ? /* @__PURE__ */ n.jsx("img", { src: y.url, alt: "当前叠加图片" }) : /* @__PURE__ */ n.jsx("i", { className: "bi bi-image" }),
      /* @__PURE__ */ n.jsxs("div", { children: [
        /* @__PURE__ */ n.jsx("b", { children: y.name }),
        /* @__PURE__ */ n.jsx("small", { children: "可换成不同产品或细节图" }),
        /* @__PURE__ */ n.jsxs("select", { "aria-label": "选择项目图片", className: "overlay-image-choice", value: y.image_id || "", onChange: (w) => {
          const m = j.find((o) => o.id === w.target.value);
          m && (h({ image_id: m.id, url: m.url, name: m.name }), x(y.start_frame + 12));
        }, children: [
          /* @__PURE__ */ n.jsx("option", { value: "", children: "选择项目图片" }),
          j.map((w) => /* @__PURE__ */ n.jsx("option", { value: w.id, children: w.name }, w.id))
        ] })
      ] })
    ] }),
    /* @__PURE__ */ n.jsxs("label", { className: "field", children: [
      "更换这张图片 · 选择图片",
      /* @__PURE__ */ n.jsx("input", { "aria-label": "上传叠加图片", type: "file", accept: "image/jpeg,image/png,image/webp", onChange: L, disabled: U })
    ] }),
    /* @__PURE__ */ n.jsx("p", { className: "micro", children: "JPG、PNG、WebP · 上传后保存到本项目，其他项目无法读取。" }),
    /* @__PURE__ */ n.jsxs("div", { className: "plan-note", children: [
      /* @__PURE__ */ n.jsx("i", { className: "bi bi-layers" }),
      /* @__PURE__ */ n.jsxs("div", { children: [
        /* @__PURE__ */ n.jsx("b", { children: "加入细节，保留原有包装" }),
        /* @__PURE__ */ n.jsx("p", { children: "图片独立合成，其他包装效果保留。拖动图片中部定位，右下角手柄缩放。" })
      ] })
    ] }),
    /* @__PURE__ */ n.jsxs("div", { className: "sample-section", children: [
      /* @__PURE__ */ n.jsxs("div", { className: "section-title", children: [
        /* @__PURE__ */ n.jsx("h4", { children: "放在哪一段？" }),
        /* @__PURE__ */ n.jsxs("span", { children: [
          s.length,
          "段实际素材"
        ] })
      ] }),
      /* @__PURE__ */ n.jsx(nn, { rows: s, selected: V.number, label: "叠加片段", onSelect: (w) => {
        h({ scene_number: w.number, start_frame: 0, end_frame: w.frames }), te(24), P((m) => m + 1);
      } }),
      /* @__PURE__ */ n.jsx("p", { className: "micro", children: V.spoken_text })
    ] }),
    /* @__PURE__ */ n.jsxs("details", { className: "overlay-more", children: [
      /* @__PURE__ */ n.jsx("summary", { children: "前后层级 · 图片重叠时使用" }),
      /* @__PURE__ */ n.jsx("button", { className: "none-button", disabled: ((ne = _.at(-1)) == null ? void 0 : ne.id) === p, onClick: () => ue(1), children: "往前一层" }),
      " ",
      /* @__PURE__ */ n.jsx("button", { className: "none-button", disabled: ((me = _[0]) == null ? void 0 : me.id) === p, onClick: () => ue(-1), children: "往后一层" })
    ] }),
    /* @__PURE__ */ n.jsxs("div", { className: "section-title", children: [
      /* @__PURE__ */ n.jsx("h4", { children: "位置与大小" }),
      /* @__PURE__ */ n.jsx("span", { children: "拖动画面里的图片" })
    ] }),
    /* @__PURE__ */ n.jsx("div", { className: "frame-presets", children: [[25, 28, "左上"], [75, 28, "右上"], [50, 50, "居中"]].map(([w, m, o]) => /* @__PURE__ */ n.jsx("button", { className: "none-button", onClick: () => {
      h({ x: w, y: m }), x(y.start_frame + 12);
    }, children: o }, o)) }),
    /* @__PURE__ */ n.jsxs("p", { className: "micro", children: [
      "横向 ",
      Math.round(y.x),
      "% · 纵向 ",
      Math.round(y.y),
      "% · 固定位置，不自动追踪"
    ] }),
    /* @__PURE__ */ n.jsxs("label", { className: "field", children: [
      "图片大小 · 画面宽度 ",
      y.width,
      "%",
      /* @__PURE__ */ n.jsx("input", { "aria-label": "叠加图片大小", type: "range", min: "15", max: "55", value: y.width, onChange: (w) => h({ width: +w.target.value }) })
    ] }),
    /* @__PURE__ */ n.jsxs("div", { className: "field-row", children: [
      /* @__PURE__ */ n.jsxs("label", { className: "field", children: [
        "圆角 · ",
        y.radius,
        "px",
        /* @__PURE__ */ n.jsx("input", { "aria-label": "图片圆角", type: "range", min: "0", max: "48", value: y.radius, onChange: (w) => h({ radius: +w.target.value }) })
      ] }),
      /* @__PURE__ */ n.jsxs("label", { className: "field", children: [
        "边框 · ",
        y.border,
        "px",
        /* @__PURE__ */ n.jsx("input", { "aria-label": "图片边框粗细", type: "range", min: "0", max: "8", value: y.border, onChange: (w) => h({ border: +w.target.value }) })
      ] })
    ] }),
    /* @__PURE__ */ n.jsxs("label", { className: "field", children: [
      "边框颜色",
      /* @__PURE__ */ n.jsx("input", { "aria-label": "图片边框颜色", type: "color", value: y.color, onChange: (w) => h({ color: w.target.value }) })
    ] }),
    /* @__PURE__ */ n.jsxs("div", { className: "info-timing", children: [
      /* @__PURE__ */ n.jsxs("div", { className: "section-title", children: [
        /* @__PURE__ */ n.jsx("h4", { children: "什么时候出现？" }),
        /* @__PURE__ */ n.jsxs("span", { children: [
          "片段内时间 · ",
          It(V.frames),
          "秒"
        ] })
      ] }),
      /* @__PURE__ */ n.jsxs("div", { className: "field-row", children: [
        /* @__PURE__ */ n.jsxs("label", { className: "field", children: [
          "开始（秒）",
          /* @__PURE__ */ n.jsx("input", { "aria-label": "叠加开始秒数", type: "number", step: "0.05", value: It(y.start_frame), onChange: (w) => q("start_frame", Math.max(0, Math.min(y.end_frame - 36, Math.round(+w.target.value * 24)))) })
        ] }),
        /* @__PURE__ */ n.jsxs("label", { className: "field", children: [
          "结束（秒）",
          /* @__PURE__ */ n.jsx("input", { "aria-label": "叠加结束秒数", type: "number", step: "0.05", value: It(y.end_frame), onChange: (w) => q("end_frame", Math.min(V.frames, Math.max(y.start_frame + 36, Math.round(+w.target.value * 24)))) })
        ] })
      ] }),
      /* @__PURE__ */ n.jsx(_i, { label: "拖动调整叠加区间", card: { ...y, id: "overlay" }, cards: [], frames: V.frames, onChange: q }),
      /* @__PURE__ */ n.jsxs("div", { className: "info-time-caption", children: [
        /* @__PURE__ */ n.jsxs("span", { children: [
          "整片 ",
          It(V.start_frame + y.start_frame),
          "–",
          It(V.start_frame + y.end_frame),
          "秒"
        ] }),
        /* @__PURE__ */ n.jsxs("b", { children: [
          "显示 ",
          It(y.end_frame - y.start_frame),
          "秒"
        ] })
      ] })
    ] }),
    fe && /* @__PURE__ */ n.jsx("p", { className: "error-text", role: "alert", children: fe }),
    /* @__PURE__ */ n.jsxs("details", { className: "scope-details", children: [
      /* @__PURE__ */ n.jsx("summary", { children: "AI助手能怎样帮我？" }),
      /* @__PURE__ */ n.jsx("p", { children: "助手可建议现有图片、片段、位置、大小与时间。用户查看和采用后才制作。不生成新图片，不替换人物或背景，也不自动避让字幕和人物。" })
    ] })
  ] });
  return f({ preview: S, controls: _.length ? Z : /* @__PURE__ */ n.jsxs(n.Fragment, { children: [
    /* @__PURE__ */ n.jsxs("div", { className: "section-top", children: [
      /* @__PURE__ */ n.jsxs("div", { children: [
        /* @__PURE__ */ n.jsx("h3", { children: "把想展示的图片，放在一起" }),
        /* @__PURE__ */ n.jsx("p", { children: "最多3张图片，可同时出现，也可先后展示。" })
      ] }),
      /* @__PURE__ */ n.jsxs("button", { className: "none-button overlay-add", disabled: _.length >= 3, onClick: I, children: [
        /* @__PURE__ */ n.jsx("i", { className: "bi bi-plus-lg" }),
        " 添加图片"
      ] })
    ] }),
    /* @__PURE__ */ n.jsxs("div", { className: "plan-note", children: [
      /* @__PURE__ */ n.jsx("i", { className: "bi bi-images" }),
      /* @__PURE__ */ n.jsxs("div", { children: [
        /* @__PURE__ */ n.jsx("b", { children: "还没有叠加图片" }),
        /* @__PURE__ */ n.jsx("p", { children: "点击「添加图片」开始，原视频始终保留。" })
      ] })
    ] })
  ] }), valid: !U && _.every((w) => w.image_id && w.url) });
}
function ov({ rows: s, cards: d, settings: b, children: f, effects: E, setEffects: z, onTouch: O = () => {
}, framing: U = [], recipes: M = [], overlays: j = [] }) {
  const [_, y] = T.useState(s[Math.min(1, s.length - 1)].number), [p, Y] = T.useState(48), [X, I] = T.useState(!1), [ie, J] = T.useState(!1), [ue, V] = T.useState(0.25), [W, te] = T.useState(""), le = { scene_number: _, kind: "arrow", x: 70, y: 48, size: 30, angle: 135, color: "#d2f36b", strength: 65, start_frame: 0, end_frame: s.find((k) => k.number === _).frames }, P = E.find((k) => k.scene_number === _) || le, { kind: F, x: ee, y: fe, size: H, angle: h, color: x, strength: q, start_frame: L, end_frame: S } = P, Z = (k) => {
    O(), z((Se) => [...Se.filter((tt) => tt.scene_number !== _), rm(P, k)]), J(!1);
  }, ne = (k) => Z({ kind: k }), me = (k) => Z({ x: k }), w = (k) => Z({ y: k }), m = (k) => Z({ size: k }), o = (k) => Z({ angle: k }), D = (k) => Z({ color: k }), G = (k) => Z({ strength: k }), ae = (k) => Z({ start_frame: k }), ce = (k) => Z({ end_frame: k }), de = T.useRef(), Oe = T.useRef(), [_e, nt] = T.useState(null), [ve, ge] = T.useState(null), qe = T.useCallback((k) => {
    Oe.current = k, nt(k);
  }, []), ye = T.useCallback((k) => {
    de.current = k, ge(k);
  }, []), pe = s.find((k) => k.number === _), vt = E.some((k) => k.scene_number === _) && p >= L && p < S && !ie, Ve = d.find((k) => k.scene_number === _ && p >= k.start_frame && p < k.end_frame), Re = (k) => {
    var Se;
    k = Math.max(0, Math.min(pe.frames - 1, k)), Y(k), ((Se = de.current) == null ? void 0 : Se.readyState) >= 1 && (de.current.currentTime = (pe.in_frame + k) / 24);
  };
  T.useEffect(() => {
    if (!_e) return;
    const k = new ResizeObserver((Se) => V(Se[0].contentRect.width / 720));
    return k.observe(_e), () => k.disconnect();
  }, [_e]), T.useEffect(() => {
    if (!ve) return;
    let k;
    const Se = () => {
      let va = Math.round(de.current.currentTime * 24) - pe.in_frame;
      va >= pe.frames && (de.current.pause(), va = pe.frames - 1), Y(Math.max(0, va)), de.current.paused || (k = requestAnimationFrame(Se));
    }, tt = () => {
      I(!0), k = requestAnimationFrame(Se);
    }, Pt = () => I(!1), $l = ve;
    return $l.addEventListener("play", tt), $l.addEventListener("pause", Pt), () => {
      cancelAnimationFrame(k), $l.pause(), $l.removeEventListener("play", tt), $l.removeEventListener("pause", Pt);
    };
  }, [_, ve]);
  const Dt = (k) => {
    const Se = Oe.current.getBoundingClientRect();
    Z({ x: Math.round(Math.max(5, Math.min(95, (k.clientX - Se.left) / Se.width * 100))), y: Math.round(Math.max(5, Math.min(95, (k.clientY - Se.top) / Se.height * 100))) });
  }, Jl = (k, Se) => {
    k === "start_frame" ? ae(Se) : ce(Se), Re(k === "start_frame" ? Se : Se - 1);
  }, kl = ha(U.find((k) => k.scene_number === _), p, pe.frames), yl = M.find((k) => k.scene.number === _), Si = /* @__PURE__ */ n.jsxs(n.Fragment, { children: [
    /* @__PURE__ */ n.jsxs("div", { className: "eyebrow", children: [
      "边看边调整 ",
      /* @__PURE__ */ n.jsx("span", { children: "当前片段预览" })
    ] }),
    /* @__PURE__ */ n.jsxs("div", { className: "pack-player compact-player", children: [
      /* @__PURE__ */ n.jsxs("div", { className: "portrait-stage emph-stage", ref: qe, children: [
        /* @__PURE__ */ n.jsx("video", { ref: ye, src: pe.raw_url, style: { transformOrigin: "0 0", transform: `translate(${kl.x * 100}%,${kl.y * 100}%) scale(${kl.scale})` }, playsInline: !0, preload: "auto", onError: () => te("原始片段暂时无法读取，请重新打开包装设置。"), onLoadedMetadata: () => Re(p) }),
        !ie && /* @__PURE__ */ n.jsx(an, { cards: d, overlays: j, scene: pe, framing: U.find((k) => k.scene_number === pe.number), effect: E.find((k) => k.scene_number === _), frame: p }),
        /* @__PURE__ */ n.jsxs("div", { className: "emph-text", style: { transform: `scale(${ue})` }, children: [
          /* @__PURE__ */ n.jsx(tn, { scene: Ve != null && Ve.replace_product_info ? { ...pe, original_subtitle: pe.spoken_text } : pe, settings: (yl == null ? void 0 : yl.settings) || b, original: (yl == null ? void 0 : yl.original) || !1, frame: p }),
          /* @__PURE__ */ n.jsx(ln, { card: Ve }),
          /* @__PURE__ */ n.jsx(eu, { layers: j.filter((k) => k.scene_number === pe.number), frame: p })
        ] }),
        /* @__PURE__ */ n.jsx("button", { className: "emph-drag", "aria-label": "拖动强调位置", style: { left: `${ee}%`, top: `${fe}%` }, onPointerDown: (k) => {
          de.current.pause(), k.currentTarget.setPointerCapture(k.pointerId), Dt(k);
        }, onPointerMove: (k) => {
          k.currentTarget.hasPointerCapture(k.pointerId) && Dt(k);
        }, onPointerUp: (k) => k.currentTarget.releasePointerCapture(k.pointerId), onKeyDown: (k) => {
          const Se = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] }[k.key];
          Se && (k.preventDefault(), me(Math.max(5, Math.min(95, ee + Se[0]))), w(Math.max(5, Math.min(95, fe + Se[1]))));
        }, children: "＋" })
      ] }),
      /* @__PURE__ */ n.jsxs("div", { className: "transport", children: [
        /* @__PURE__ */ n.jsx("button", { "aria-label": X ? "暂停强调预览" : "播放强调预览", onClick: () => {
          X ? de.current.pause() : (p >= pe.frames - 1 && Re(0), de.current.play().catch(() => te("视频暂未能播放，请再点击播放。")));
        }, children: /* @__PURE__ */ n.jsx("i", { className: `bi bi-${X ? "pause-fill" : "play-fill"}` }) }),
        /* @__PURE__ */ n.jsxs("span", { children: [
          (p / 24).toFixed(1),
          " / ",
          (pe.frames / 24).toFixed(1)
        ] }),
        /* @__PURE__ */ n.jsx("input", { "aria-label": "强调预览播放进度", type: "range", min: "0", max: pe.frames - 1, value: p, onChange: (k) => Re(+k.target.value) })
      ] })
    ] }),
    W && /* @__PURE__ */ n.jsx("p", { className: "error-text", role: "alert", children: W }),
    /* @__PURE__ */ n.jsx("span", { className: "info-live-state", children: ie ? "原画对比" : vt ? "强调效果显示中" : "当前时间不在显示区间" }),
    /* @__PURE__ */ n.jsxs("div", { className: "preview-shortcuts", children: [
      /* @__PURE__ */ n.jsx("button", { className: "none-button", onClick: () => J(!ie), children: ie ? "看强调效果" : "对比原画" }),
      /* @__PURE__ */ n.jsx("button", { className: "none-button", onClick: () => Re(L), children: "重播区间" })
    ] }),
    /* @__PURE__ */ n.jsxs("div", { className: "length-note", children: [
      /* @__PURE__ */ n.jsx("i", { className: "bi bi-clock" }),
      /* @__PURE__ */ n.jsxs("div", { children: [
        "第",
        _,
        "段 · ",
        (pe.start_frame / 24).toFixed(2),
        "–",
        (pe.end_frame / 24).toFixed(2),
        "秒",
        /* @__PURE__ */ n.jsx("strong", { children: "整片时长不变" })
      ] })
    ] }),
    /* @__PURE__ */ n.jsx("p", { className: "preview-footnote", children: "拖动画面上的＋定位。固定位置，不自动追踪；字幕与信息卡独立显示。采用后本地合成，原声、音乐与品牌片尾保留。" })
  ] }), mt = /* @__PURE__ */ n.jsxs(n.Fragment, { children: [
    /* @__PURE__ */ n.jsxs("div", { className: "section-top", children: [
      /* @__PURE__ */ n.jsxs("div", { children: [
        /* @__PURE__ */ n.jsx("h3", { children: "让重点，一眼就看见" }),
        /* @__PURE__ */ n.jsx("p", { children: "指向细节，圈出重点，或让背景安静下来。" })
      ] }),
      /* @__PURE__ */ n.jsxs("label", { className: "switch-label", children: [
        /* @__PURE__ */ n.jsx("input", { type: "checkbox", checked: E.some((k) => k.scene_number === _), onChange: (k) => {
          O(), z((Se) => k.target.checked ? [...Se.filter((tt) => tt.scene_number !== _), P] : Se.filter((tt) => tt.scene_number !== _));
        } }),
        "使用强调"
      ] })
    ] }),
    /* @__PURE__ */ n.jsx("div", { className: "caption-modes", role: "radiogroup", "aria-label": "内容强调方式", children: [["arrow", "箭头指示", "arrow-up-right", "指向一个具体细节"], ["circle", "圈选重点", "circle", "圈出需要注意的区域"], ["spot", "局部聚光", "brightness-high", "压暗其他画面"]].map(([k, Se, tt, Pt]) => /* @__PURE__ */ n.jsxs("button", { className: `caption-option ${F === k ? "selected" : ""}`, role: "radio", "aria-checked": F === k, onClick: () => {
      ne(k), J(!1), Re(Math.max(L, Math.min(p, S - 1)));
    }, children: [
      /* @__PURE__ */ n.jsx("div", { className: "emph-art", children: /* @__PURE__ */ n.jsx("i", { className: `bi bi-${tt}` }) }),
      /* @__PURE__ */ n.jsxs("div", { className: "template-name", children: [
        /* @__PURE__ */ n.jsx("b", { children: Se }),
        /* @__PURE__ */ n.jsx("i", { className: `bi bi-${F === k ? "check-circle-fill" : "circle"}` })
      ] }),
      /* @__PURE__ */ n.jsx("small", { children: Pt })
    ] }, k)) }),
    /* @__PURE__ */ n.jsxs("div", { className: "plan-note", children: [
      /* @__PURE__ */ n.jsx("i", { className: "bi bi-layers" }),
      /* @__PURE__ */ n.jsxs("div", { children: [
        /* @__PURE__ */ n.jsx("b", { children: "强调画面，保留阅读层次" }),
        /* @__PURE__ */ n.jsx("p", { children: "字幕与信息卡保持清晰。固定位置，不跟随移动目标。" })
      ] })
    ] }),
    /* @__PURE__ */ n.jsxs("div", { className: "sample-section", children: [
      /* @__PURE__ */ n.jsxs("div", { className: "section-title", children: [
        /* @__PURE__ */ n.jsx("h4", { children: "强调哪一段？" }),
        /* @__PURE__ */ n.jsxs("span", { children: [
          s.length,
          "段实际素材"
        ] })
      ] }),
      /* @__PURE__ */ n.jsx(nn, { rows: s, selected: _, label: "强调片段", onSelect: (k) => {
        y(k.number), Y(24);
      } }),
      /* @__PURE__ */ n.jsx("p", { className: "micro", children: pe.spoken_text })
    ] }),
    /* @__PURE__ */ n.jsxs("div", { className: "section-title", children: [
      /* @__PURE__ */ n.jsx("h4", { children: "位置与大小" }),
      /* @__PURE__ */ n.jsx("span", { children: "拖动预览中的＋" })
    ] }),
    /* @__PURE__ */ n.jsx("div", { className: "frame-presets", children: [[30, 45, "偏左"], [50, 50, "居中"], [72, 48, "偏右"]].map(([k, Se, tt]) => /* @__PURE__ */ n.jsx("button", { className: "none-button", onClick: () => {
      Z({ x: k, y: Se }), Re(L + 12);
    }, children: tt }, tt)) }),
    /* @__PURE__ */ n.jsxs("p", { className: "micro", children: [
      "横向 ",
      ee,
      "% · 纵向 ",
      fe,
      "% · 箭头尖端 / 区域中心"
    ] }),
    /* @__PURE__ */ n.jsxs("label", { className: "field", children: [
      F === "arrow" ? "箭头长度" : "强调范围",
      /* @__PURE__ */ n.jsx("input", { "aria-label": "强调大小", type: "range", min: "10", max: "60", value: H, onChange: (k) => m(+k.target.value) })
    ] }),
    F === "arrow" && /* @__PURE__ */ n.jsxs("label", { className: "field", children: [
      "箭头方向 · ",
      h,
      "°",
      /* @__PURE__ */ n.jsx("input", { "aria-label": "箭头方向", type: "range", min: "0", max: "360", value: h, onChange: (k) => o(+k.target.value) })
    ] }),
    /* @__PURE__ */ n.jsxs("div", { className: "field-row", children: [
      /* @__PURE__ */ n.jsxs("label", { className: "field", children: [
        "强调颜色",
        /* @__PURE__ */ n.jsx("input", { "aria-label": "强调颜色", type: "color", value: x, onChange: (k) => D(k.target.value) })
      ] }),
      F === "spot" && /* @__PURE__ */ n.jsxs("label", { className: "field", children: [
        "背景压暗 · ",
        q,
        "%",
        /* @__PURE__ */ n.jsx("input", { "aria-label": "背景压暗强度", type: "range", min: "20", max: "85", value: q, onChange: (k) => G(+k.target.value) })
      ] })
    ] }),
    /* @__PURE__ */ n.jsxs("div", { className: "info-timing", children: [
      /* @__PURE__ */ n.jsxs("div", { className: "section-title", children: [
        /* @__PURE__ */ n.jsx("h4", { children: "什么时候出现？" }),
        /* @__PURE__ */ n.jsxs("span", { children: [
          "片段内时间 · ",
          (pe.frames / 24).toFixed(2),
          "秒"
        ] })
      ] }),
      /* @__PURE__ */ n.jsxs("div", { className: "field-row", children: [
        /* @__PURE__ */ n.jsxs("label", { className: "field", children: [
          "开始（秒）",
          /* @__PURE__ */ n.jsx("input", { "aria-label": "强调开始秒数", type: "number", step: "0.05", value: (L / 24).toFixed(2), onChange: (k) => Jl("start_frame", Math.max(0, Math.min(S - 36, Math.round(+k.target.value * 24)))) })
        ] }),
        /* @__PURE__ */ n.jsxs("label", { className: "field", children: [
          "结束（秒）",
          /* @__PURE__ */ n.jsx("input", { "aria-label": "强调结束秒数", type: "number", step: "0.05", value: (S / 24).toFixed(2), onChange: (k) => Jl("end_frame", Math.min(pe.frames, Math.max(L + 36, Math.round(+k.target.value * 24)))) })
        ] })
      ] }),
      /* @__PURE__ */ n.jsx(_i, { label: "拖动调整强调区间", card: { id: "emph", scene_number: _, start_frame: L, end_frame: S }, cards: [], frames: pe.frames, onChange: Jl }),
      /* @__PURE__ */ n.jsxs("div", { className: "info-time-caption", children: [
        /* @__PURE__ */ n.jsxs("span", { children: [
          "整片 ",
          ((pe.start_frame + L) / 24).toFixed(2),
          "–",
          ((pe.start_frame + S) / 24).toFixed(2),
          "秒"
        ] }),
        /* @__PURE__ */ n.jsxs("b", { children: [
          "显示 ",
          ((S - L) / 24).toFixed(2),
          "秒"
        ] })
      ] })
    ] }),
    /* @__PURE__ */ n.jsxs("details", { className: "scope-details", children: [
      /* @__PURE__ */ n.jsx("summary", { children: "AI助手能怎样帮我？" }),
      /* @__PURE__ */ n.jsx("p", { children: "助手可建议片段、位置、样式与显示时间，用户采用后才执行。不自动追踪，不保证移动目标始终在强调区域。" })
    ] })
  ] });
  return f({ preview: Si, controls: /* @__PURE__ */ n.jsxs(n.Fragment, { children: [
    mt,
    /* @__PURE__ */ n.jsx("button", { className: "none-button", onClick: () => {
      O(), z((k) => k.filter((Se) => Se.scene_number !== _));
    }, children: "移除此段强调" })
  ] }), valid: E.every((k) => {
    const Se = s.find((tt) => tt.number === k.scene_number);
    return Se && k.start_frame >= 0 && k.end_frame <= Se.frames && k.end_frame - k.start_frame >= 36;
  }) });
}
var dv = sm();
const Kl = ({ name: s }) => /* @__PURE__ */ n.jsx("i", { className: `bi bi-${s}`, "aria-hidden": "true" }), mv = { plain: "普通字幕", highlight: "逐词高亮", phrase: "短语弹出" }, um = (s) => `${Math.floor(s / 60)}:${String(Math.floor(s % 60)).padStart(2, "0")}`;
function hv({ scene: s, settings: d, original: b, safe: f, comparisonUrl: E, effect: z = null, cards: O = [], recipe: U = null, emphasis: M = [], overlays: j = [] }) {
  const _ = b ? s.start_frame : s.in_frame, y = T.useRef(), p = T.useRef(), [Y, X] = T.useState(0), [I, ie] = T.useState(!1), [J, ue] = T.useState(!1), [V, W] = T.useState(0.28), [te, le] = T.useState("");
  T.useEffect(() => {
    const x = new ResizeObserver((q) => W(q[0].contentRect.width / 720));
    return x.observe(p.current), () => x.disconnect();
  }, []), T.useEffect(() => {
    X(0), ie(!1), le("");
    const x = y.current;
    x.pause(), x.load();
  }, [s.clip_id, b]), T.useEffect(() => {
    const x = y.current;
    let q;
    const L = () => {
      let ne = Math.round(x.currentTime * 24) - _;
      ne >= s.frames && (x.pause(), x.currentTime = _ / 24, ne = 0, ie(!1)), X(Math.max(0, ne)), x.paused || (q = requestAnimationFrame(L));
    }, S = () => {
      ie(!0), q = requestAnimationFrame(L);
    }, Z = () => ie(!1);
    return x.addEventListener("play", S), x.addEventListener("pause", Z), () => {
      cancelAnimationFrame(q), x.removeEventListener("play", S), x.removeEventListener("pause", Z);
    };
  }, [s, b]);
  const P = (x) => {
    y.current.currentTime = (_ + x) / 24, X(x);
  }, F = () => {
    I ? y.current.pause() : ((y.current.currentTime < _ / 24 || y.current.currentTime >= (_ + s.frames) / 24) && P(0), y.current.play().catch(() => le("视频未能播放，请重新打开预览。")));
  }, ee = ha(b ? null : z, Y, s.frames), fe = O.find((x) => x.scene_number === s.number && Y >= x.start_frame && Y < x.end_frame), H = U != null && U.scene ? { ...s, ...U.scene } : s, h = fe != null && fe.replace_product_info ? { ...H, original_subtitle: H.spoken_text } : H;
  return /* @__PURE__ */ n.jsxs(n.Fragment, { children: [
    /* @__PURE__ */ n.jsxs("div", { className: "pack-player compact-player", children: [
      /* @__PURE__ */ n.jsxs("div", { className: "portrait-stage", ref: p, children: [
        /* @__PURE__ */ n.jsx("video", { ref: y, src: b ? E : s.raw_url, style: { transformOrigin: "0 0", transform: `translate(${ee.x * 100}%,${ee.y * 100}%) scale(${ee.scale})` }, muted: J, playsInline: !0, preload: "metadata", onLoadedMetadata: () => P(0), onError: () => le("原始片段暂不可用，请重新检查素材。") }),
        !b && /* @__PURE__ */ n.jsx(an, { cards: O, overlays: j, scene: s, framing: z, effect: M.find((x) => x.scene_number === s.number), frame: Y }),
        /* @__PURE__ */ n.jsx("div", { style: { position: "absolute", left: 0, top: 0, width: 720, height: 1280, transform: `scale(${V})`, transformOrigin: "top left" }, children: !b && /* @__PURE__ */ n.jsxs(n.Fragment, { children: [
          /* @__PURE__ */ n.jsx(tn, { scene: h, settings: (U == null ? void 0 : U.settings) || d, original: (U == null ? void 0 : U.original) || !1, frame: Y }),
          /* @__PURE__ */ n.jsx(ln, { card: fe }),
          /* @__PURE__ */ n.jsx(eu, { layers: j.filter((x) => x.scene_number === s.number), frame: Y })
        ] }) }),
        f && /* @__PURE__ */ n.jsx("div", { className: "safe-area", children: "字幕安全区 · 辅助线" })
      ] }),
      /* @__PURE__ */ n.jsxs("div", { className: "transport", children: [
        /* @__PURE__ */ n.jsx("button", { "aria-label": I ? "暂停字幕预览" : "播放字幕预览", onClick: F, children: /* @__PURE__ */ n.jsx(Kl, { name: I ? "pause-fill" : "play-fill" }) }),
        /* @__PURE__ */ n.jsxs("span", { children: [
          um(Y / 24),
          " / ",
          um(s.frames / 24)
        ] }),
        /* @__PURE__ */ n.jsx("input", { type: "range", "aria-label": "字幕预览播放进度", min: "0", max: s.frames - 1, step: "1", value: Math.min(Y, s.frames - 1), onChange: (x) => P(Number(x.target.value)) }),
        /* @__PURE__ */ n.jsx("button", { "aria-label": J ? "开启预览声音" : "静音字幕预览", onClick: () => ue(!J), children: /* @__PURE__ */ n.jsx(Kl, { name: J ? "volume-mute" : "volume-up" }) })
      ] })
    ] }),
    te && /* @__PURE__ */ n.jsx("p", { className: "error-text", role: "alert", children: te }),
    /* @__PURE__ */ n.jsx("div", { className: "preview-shortcuts", children: /* @__PURE__ */ n.jsx("button", { className: "none-button", onClick: () => P(0), children: "重播片段" }) })
  ] });
}
function vv({ data: s, draft: d, setDraft: b, onPrepare: f, busy: E, error: z, onTouch: O = () => {
}, effects: U = [], cards: M = [], recipes: j = [], emphasis: _ = [], overlays: y = [], children: p }) {
  const [Y, X] = T.useState(Math.max(0, (d.scene || 1) - 1)), [I, ie] = T.useState(!1), [J, ue] = T.useState(!1), V = s.alignment.status === "ready", W = V ? s.alignment.scenes : s.scenes, te = W[Y] || W[0], le = (h, x) => {
    (h !== "scene" || d.scope === "scene") && O(), b((q) => ({ ...q, [h]: x })), ie(!1);
  }, P = te.start_frame / 24, F = te.end_frame / 24, ee = Array.from(te.spoken_text || te.title).slice(0, 8).join(""), fe = /* @__PURE__ */ n.jsxs(n.Fragment, { children: [
    /* @__PURE__ */ n.jsxs("div", { className: "eyebrow", children: [
      "边看边调整 ",
      /* @__PURE__ */ n.jsx("span", { children: V ? "实际语音同步" : "原始片段" })
    ] }),
    /* @__PURE__ */ n.jsx(hv, { overlays: y, emphasis: _, scene: te, settings: d, original: I || !V || !te.spoken_text, safe: J, comparisonUrl: s.comparison_url, effect: U.find((h) => h.scene_number === te.number), cards: M, recipe: j.find((h) => h.scene.number === te.number) }),
    /* @__PURE__ */ n.jsx("div", { className: "preview-shortcuts", children: /* @__PURE__ */ n.jsx("button", { className: "none-button", disabled: !V, onClick: () => ie(!I), children: I ? "看方案效果" : "看原字幕" }) }),
    /* @__PURE__ */ n.jsxs("div", { className: "length-note", children: [
      /* @__PURE__ */ n.jsx(Kl, { name: "clock" }),
      /* @__PURE__ */ n.jsxs("div", { children: [
        /* @__PURE__ */ n.jsxs("b", { children: [
          "第",
          te.number,
          "段 · ",
          P.toFixed(2),
          "–",
          F.toFixed(2),
          "秒"
        ] }),
        /* @__PURE__ */ n.jsx("strong", { children: "整片时长不变" })
      ] })
    ] }),
    /* @__PURE__ */ n.jsx("p", { className: "preview-footnote", children: "预览当前原声片段，不重复播放整片配乐。采用后保留原声、音乐与品牌片尾；预览和导出共用字幕规则。" })
  ] }), H = /* @__PURE__ */ n.jsxs(n.Fragment, { children: [
    /* @__PURE__ */ n.jsx("div", { className: "section-top", children: /* @__PURE__ */ n.jsxs("div", { children: [
      /* @__PURE__ */ n.jsx("h3", { children: "选一种适合这支视频的字幕" }),
      /* @__PURE__ */ n.jsx("p", { children: "完整表达不变，只改变出现方式。" })
    ] }) }),
    !V && /* @__PURE__ */ n.jsxs("div", { className: "recommendation", role: "status", children: [
      /* @__PURE__ */ n.jsx(Kl, { name: "soundwave" }),
      /* @__PURE__ */ n.jsxs("p", { children: [
        /* @__PURE__ */ n.jsx("b", { children: ["queued", "running"].includes(s.alignment.status) ? `正在本地准备逐词同步 · ${s.alignment.completed_scenes || 0}/${s.alignment.total_scenes || W.length}段…` : s.alignment.status === "failed" ? "逐词同步未完成" : "先准备实际语音时间" }),
        s.alignment.error || "依据确认台词与实际原声对齐，不调用收费AI，不更改成片。可先选样式，同步完成后预览并采用。",
        !["queued", "running"].includes(s.alignment.status) && /* @__PURE__ */ n.jsx("button", { className: "outline-button", onClick: f, disabled: E, children: "准备逐词同步（本地）" })
      ] })
    ] }),
    /* @__PURE__ */ n.jsx("fieldset", { disabled: E, children: /* @__PURE__ */ n.jsx("div", { className: "caption-modes", role: "radiogroup", "aria-label": "字幕呈现方式", children: [["plain", "短句完整显示，安静易读"], ["highlight", "句子保留，当前词语突出"], ["phrase", "按意思分组，跟随口播出现"]].map(([h, x]) => /* @__PURE__ */ n.jsxs("button", { className: `caption-option ${d.mode === h ? "selected" : ""}`, role: "radio", "aria-checked": d.mode === h, onClick: () => le("mode", h), children: [
      /* @__PURE__ */ n.jsx("div", { className: "mode-preview", children: h === "plain" ? ee : h === "highlight" ? /* @__PURE__ */ n.jsxs(n.Fragment, { children: [
        ee.slice(0, -2),
        /* @__PURE__ */ n.jsx("span", { children: ee.slice(-2) })
      ] }) : /* @__PURE__ */ n.jsx("span", { className: "phrase-example", children: ee }) }),
      /* @__PURE__ */ n.jsxs("div", { className: "template-name", children: [
        /* @__PURE__ */ n.jsx("b", { children: mv[h] }),
        /* @__PURE__ */ n.jsx(Kl, { name: d.mode === h ? "check-circle-fill" : "circle" })
      ] }),
      /* @__PURE__ */ n.jsx("small", { children: x }),
      h === "highlight" && /* @__PURE__ */ n.jsx("span", { className: "recommend", children: "推荐样式" })
    ] }, h)) }) }),
    /* @__PURE__ */ n.jsxs("div", { className: "recommendation", children: [
      /* @__PURE__ */ n.jsx(Kl, { name: "stars" }),
      /* @__PURE__ */ n.jsxs("p", { children: [
        /* @__PURE__ */ n.jsx("b", { children: d.mode === "highlight" ? "让观众跟得上重点" : d.mode === "phrase" ? "让字幕跟着意思出现" : "清楚，始终是第一位" }),
        "依据确认口播断句；商品信息独立保留，不混进逐词高亮。"
      ] })
    ] }),
    /* @__PURE__ */ n.jsxs("div", { className: "sample-section", children: [
      /* @__PURE__ */ n.jsxs("div", { className: "section-title", children: [
        /* @__PURE__ */ n.jsx("h4", { children: "看哪一段？" }),
        /* @__PURE__ */ n.jsxs("span", { children: [
          W.length,
          "段实际素材"
        ] })
      ] }),
      /* @__PURE__ */ n.jsx(nn, { rows: W, selected: te.number, label: "选择预览段落", onSelect: (h) => {
        X(W.findIndex((x) => x.number === h.number)), le("scene", h.number);
      } }),
      /* @__PURE__ */ n.jsx("p", { className: "micro", children: te.spoken_text || "此段没有口播，保留原信息文字。" })
    ] }),
    /* @__PURE__ */ n.jsxs("fieldset", { disabled: E, children: [
      /* @__PURE__ */ n.jsxs("div", { className: "field-row", children: [
        /* @__PURE__ */ n.jsxs("label", { className: "field", children: [
          "字号",
          /* @__PURE__ */ n.jsxs("select", { value: d.size, onChange: (h) => le("size", h.target.value), children: [
            /* @__PURE__ */ n.jsx("option", { value: "small", children: "小" }),
            /* @__PURE__ */ n.jsx("option", { value: "medium", children: "适中 · 推荐" }),
            /* @__PURE__ */ n.jsx("option", { value: "large", children: "大" })
          ] })
        ] }),
        /* @__PURE__ */ n.jsxs("label", { className: "field", children: [
          "位置",
          /* @__PURE__ */ n.jsxs("select", { value: d.position, onChange: (h) => le("position", h.target.value), children: [
            /* @__PURE__ */ n.jsx("option", { value: "lower", children: "下方安全区" }),
            /* @__PURE__ */ n.jsx("option", { value: "raised", children: "上移一点" })
          ] })
        ] })
      ] }),
      /* @__PURE__ */ n.jsxs("div", { className: "field-row", children: [
        /* @__PURE__ */ n.jsxs("div", { className: "field", children: [
          "强调颜色",
          /* @__PURE__ */ n.jsx("div", { className: "swatches", role: "radiogroup", "aria-label": "字幕强调颜色", children: [["#d2f36b", "品牌浅绿"], ["#ffdc78", "柔和黄"], ["#9edbf8", "浅蓝"]].map(([h, x]) => /* @__PURE__ */ n.jsx("button", { className: `swatch ${d.color === h ? "selected" : ""}`, style: { "--swatch": h }, role: "radio", "aria-label": x, "aria-checked": d.color === h, onClick: () => le("color", h), children: /* @__PURE__ */ n.jsx(Kl, { name: "check" }) }, h)) })
        ] }),
        /* @__PURE__ */ n.jsxs("label", { className: "audio-toggle safe-toggle", children: [
          /* @__PURE__ */ n.jsxs("span", { children: [
            /* @__PURE__ */ n.jsx("b", { children: "显示安全区" }),
            /* @__PURE__ */ n.jsx("small", { children: "辅助检查，不会出现在成片" })
          ] }),
          /* @__PURE__ */ n.jsx("input", { type: "checkbox", checked: J, onChange: (h) => ue(h.target.checked) })
        ] })
      ] }),
      /* @__PURE__ */ n.jsxs("details", { className: "scope-details", children: [
        /* @__PURE__ */ n.jsx("summary", { children: "应用范围与保留内容" }),
        [["all", "全部口播段落"], ["scene", "仅当前预览段落"]].map(([h, x]) => /* @__PURE__ */ n.jsxs("label", { children: [
          /* @__PURE__ */ n.jsx("input", { type: "radio", name: "subtitle-scope", checked: d.scope === h, onChange: () => le("scope", h) }),
          x
        ] }, h)),
        /* @__PURE__ */ n.jsx("p", { children: "商品信息独立保留。原声、音乐与品牌片尾不改动。" })
      ] })
    ] }),
    /* @__PURE__ */ n.jsxs("div", { className: "plan-note", children: [
      /* @__PURE__ */ n.jsx(Kl, { name: "layers" }),
      /* @__PURE__ */ n.jsxs("div", { children: [
        /* @__PURE__ */ n.jsx("b", { children: "只替换字幕，保留原始表达" }),
        /* @__PURE__ */ n.jsx("p", { children: "本轮不改台词、不翻译，也不新增人物、背景或镜头。" })
      ] })
    ] }),
    z && /* @__PURE__ */ n.jsx("p", { role: "alert", className: "error-text", children: z })
  ] });
  return p({ preview: fe, controls: H, ready: V });
}
function pv(s, d = fetch, b = Date.now) {
  let f = null, E = 0, z = null;
  return {
    async get() {
      return f && b() - E < 3e4 ? structuredClone(f) : (z || (z = (async () => {
        const O = await d(s, { headers: { Accept: "application/json" } }), U = await O.json();
        if (!O.ok) throw Error(U.message || "当前成片或品牌资料尚未准备好");
        return f = U, E = b(), U;
      })().finally(() => {
        z = null;
      })), structuredClone(await z));
    },
    updateSubtitles(O) {
      f && (f.subtitles = structuredClone(O));
    },
    addImage(O) {
      f && (f.overlay_images = [O, ...(f.overlay_images || []).filter((U) => U.id !== O.id)]);
    },
    addTrack(O) {
      f && (f.tracks = [O, ...f.tracks.filter((U) => U.id !== O.id)]);
    }
  };
}
const en = ({ name: s }) => /* @__PURE__ */ n.jsx("i", { className: `bi bi-${s}`, "aria-hidden": "true" }), Vl = (s) => (s / 24).toFixed(2), im = (s) => ({ scene_number: s.number, mode: "none", zoom: 1.18, x: 50, y: 50, start_frame: 0, end_frame: s.frames });
function bv({ scene: s, effect: d, cards: b, settings: f, original: E = !1, recipe: z = null, emphasis: O = [], overlays: U = [], picking: M = !1, onFocus: j = () => {
}, focus: _ = 0, focusKey: y = 0 }) {
  const p = T.useRef(), Y = T.useRef(), [X, I] = T.useState(0.25), [ie, J] = T.useState(_), [ue, V] = T.useState(!1), [W, te] = T.useState(!1), [le, P] = T.useState(""), F = (q) => {
    var S;
    const L = Math.max(0, Math.min(s.frames - 1, q));
    J(L), ((S = p.current) == null ? void 0 : S.readyState) >= 1 && (p.current.currentTime = (s.in_frame + L) / 24);
  };
  T.useEffect(() => {
    const q = new ResizeObserver((L) => I(L[0].contentRect.width / 720));
    return q.observe(Y.current), () => q.disconnect();
  }, []), T.useEffect(() => {
    p.current.pause(), V(!1), P(""), F(_);
  }, [s.clip_id, _, y]), T.useEffect(() => {
    const q = p.current;
    let L;
    const S = () => {
      let me = Math.round(q.currentTime * 24) - s.in_frame;
      me >= s.frames && (q.pause(), me = s.frames - 1), J(Math.max(0, me)), q.paused || (L = requestAnimationFrame(S));
    }, Z = () => {
      V(!0), L = requestAnimationFrame(S);
    }, ne = () => V(!1);
    return q.addEventListener("play", Z), q.addEventListener("pause", ne), () => {
      cancelAnimationFrame(L), q.pause(), q.removeEventListener("play", Z), q.removeEventListener("pause", ne);
    };
  }, [s.clip_id]);
  const ee = ha(E ? null : d, ie, s.frames), fe = b.find((q) => q.scene_number === s.number && ie >= q.start_frame && ie < q.end_frame), H = z != null && z.scene ? { ...s, ...z.scene, raw_url: s.raw_url } : s, h = fe != null && fe.replace_product_info ? { ...H, original_subtitle: H.spoken_text } : H, x = (q) => {
    const L = Y.current.getBoundingClientRect(), S = ((q.clientX - L.left) / L.width - ee.x) / ee.scale * 100, Z = ((q.clientY - L.top) / L.height - ee.y) / ee.scale * 100;
    j({ x: Math.round(Math.max(0, Math.min(100, S))), y: Math.round(Math.max(0, Math.min(100, Z))) });
  };
  return /* @__PURE__ */ n.jsxs(n.Fragment, { children: [
    /* @__PURE__ */ n.jsxs("div", { className: "pack-player compact-player", children: [
      /* @__PURE__ */ n.jsxs("div", { className: "portrait-stage frame-stage", ref: Y, children: [
        /* @__PURE__ */ n.jsx("video", { ref: p, src: s.raw_url, muted: W, playsInline: !0, preload: "auto", style: { transformOrigin: "0 0", transform: `translate(${ee.x * 100}%,${ee.y * 100}%) scale(${ee.scale})` }, onLoadedMetadata: () => F(_), onError: () => P("原始片段未能读取，请重新打开样本。") }),
        /* @__PURE__ */ n.jsx(an, { cards: b, overlays: U, scene: s, framing: E ? null : d, effect: O.find((q) => q.scene_number === s.number), frame: ie }),
        /* @__PURE__ */ n.jsxs("div", { className: "frame-independent-layers", style: { position: "absolute", left: 0, top: 0, width: 720, height: 1280, transform: `scale(${X})`, transformOrigin: "top left", pointerEvents: "none" }, children: [
          /* @__PURE__ */ n.jsx(tn, { scene: h, settings: (z == null ? void 0 : z.settings) || f, original: (z == null ? void 0 : z.original) || !1, frame: ie }),
          /* @__PURE__ */ n.jsx(ln, { card: fe }),
          /* @__PURE__ */ n.jsx(eu, { layers: U.filter((q) => q.scene_number === s.number), frame: ie })
        ] }),
        M && /* @__PURE__ */ n.jsxs("button", { type: "button", className: "frame-pick-overlay", "aria-label": "在画面点击选择聚焦位置", onKeyDown: (q) => {
          const L = { ArrowLeft: [-2, 0], ArrowRight: [2, 0], ArrowUp: [0, -2], ArrowDown: [0, 2] }[q.key];
          L && (q.preventDefault(), j({ x: Math.max(0, Math.min(100, d.x + L[0])), y: Math.max(0, Math.min(100, d.y + L[1])) }));
        }, onPointerDown: (q) => {
          q.preventDefault(), p.current.pause(), x(q);
        }, children: [
          /* @__PURE__ */ n.jsx("span", { className: "frame-grid" }),
          /* @__PURE__ */ n.jsx("span", { className: "frame-focus-dot", style: { left: `${(ee.scale * d.x / 100 + ee.x) * 100}%`, top: `${(ee.scale * d.y / 100 + ee.y) * 100}%` }, children: "＋" })
        ] })
      ] }),
      /* @__PURE__ */ n.jsxs("div", { className: "transport", children: [
        /* @__PURE__ */ n.jsx("button", { "aria-label": ue ? "暂停构图预览" : "播放构图预览", onClick: () => {
          ue ? p.current.pause() : (ie >= s.frames - 1 && F(0), p.current.play().catch(() => P("请再点击播放。")));
        }, children: /* @__PURE__ */ n.jsx(en, { name: ue ? "pause-fill" : "play-fill" }) }),
        /* @__PURE__ */ n.jsxs("span", { children: [
          (ie / 24).toFixed(1),
          " / ",
          (s.frames / 24).toFixed(1)
        ] }),
        /* @__PURE__ */ n.jsx("input", { type: "range", "aria-label": "构图预览播放进度", min: "0", max: s.frames - 1, value: Math.min(ie, s.frames - 1), onChange: (q) => F(Number(q.target.value)) }),
        /* @__PURE__ */ n.jsx("button", { "aria-label": W ? "开启构图预览声音" : "静音构图预览", onClick: () => te(!W), children: /* @__PURE__ */ n.jsx(en, { name: W ? "volume-mute" : "volume-up" }) })
      ] })
    ] }),
    /* @__PURE__ */ n.jsx("span", { className: "info-live-state", children: E ? "原画对比" : `${d.mode === "none" ? "保持原画" : d.mode === "fixed" ? "局部放大" : "缓慢推近"} · 当前 ${ee.scale.toFixed(2)}×` }),
    le && /* @__PURE__ */ n.jsx("p", { className: "error-text", role: "alert", children: le })
  ] });
}
function yv({ rows: s, effects: d, setEffects: b, cards: f, settings: E, recipes: z = [], emphasis: O = [], overlays: U = [], onTouch: M = () => {
}, children: j }) {
  const [_, y] = T.useState(s[Math.min(1, s.length - 1)].number), [p, Y] = T.useState(!1), [X, I] = T.useState(!1), [ie, J] = T.useState(0), [ue, V] = T.useState(0), W = s.find((H) => H.number === _) || s[0], te = d.find((H) => H.scene_number === W.number) || im(W), le = d.every((H) => {
    const h = s.find((x) => x.number === H.scene_number);
    return h && H.start_frame >= 0 && H.end_frame <= h.frames && H.end_frame - H.start_frame >= 36;
  }), P = (H) => {
    M(), b((h) => h.some((x) => x.scene_number === W.number) ? h.map((x) => x.scene_number === W.number ? { ...x, ...H } : x) : [...h, { ...te, ...H }]), Y(!1);
  }, F = (H, h) => {
    P({ [H]: h }), J(Math.max(0, H === "end_frame" ? h - 1 : h)), V((x) => x + 1);
  }, ee = /* @__PURE__ */ n.jsxs(n.Fragment, { children: [
    /* @__PURE__ */ n.jsxs("div", { className: "eyebrow", children: [
      "边看边调整 ",
      /* @__PURE__ */ n.jsx("span", { children: "当前片段预览" })
    ] }),
    /* @__PURE__ */ n.jsx(bv, { scene: W, effect: te, emphasis: O, overlays: U, recipe: z.find((H) => H.scene.number === W.number), cards: f, settings: E, original: p, picking: X && !p, onFocus: P, focus: ie, focusKey: ue }),
    /* @__PURE__ */ n.jsxs("div", { className: "preview-shortcuts", children: [
      X && /* @__PURE__ */ n.jsx("button", { className: "none-button", onClick: () => I(!1), children: "完成画面定位" }),
      /* @__PURE__ */ n.jsx("button", { className: `none-button ${p ? "selected" : ""}`, onClick: () => Y(!p), children: p ? "看构图效果" : "对比原画" }),
      /* @__PURE__ */ n.jsx("button", { className: "none-button", onClick: () => {
        J(te.start_frame), V((H) => H + 1);
      }, children: "重播效果" })
    ] }),
    /* @__PURE__ */ n.jsxs("div", { className: "length-note", children: [
      /* @__PURE__ */ n.jsx(en, { name: "clock" }),
      /* @__PURE__ */ n.jsxs("div", { children: [
        "第",
        W.number,
        "段 · ",
        Vl(W.start_frame),
        "–",
        Vl(W.end_frame),
        "秒",
        /* @__PURE__ */ n.jsx("strong", { children: "整片时长不变" })
      ] })
    ] }),
    /* @__PURE__ */ n.jsx("p", { className: "preview-footnote", children: "预览当前原声片段，字幕和信息卡独立保留。采用后统一生成新预览，保留音乐与品牌片尾。" })
  ] }), fe = /* @__PURE__ */ n.jsxs(n.Fragment, { children: [
    /* @__PURE__ */ n.jsxs("div", { className: "section-top", children: [
      /* @__PURE__ */ n.jsxs("div", { children: [
        /* @__PURE__ */ n.jsx("h3", { children: "让观众，看清你想说的重点" }),
        /* @__PURE__ */ n.jsx("p", { children: "从原画出发，轻轻靠近人物或产品。" })
      ] }),
      /* @__PURE__ */ n.jsx("span", { className: "sample-badge", children: "本地制作" })
    ] }),
    /* @__PURE__ */ n.jsx("div", { className: "caption-modes frame-modes", role: "radiogroup", "aria-label": "画面构图方式", children: [["none", "保持原画", "沿用现有构图"], ["fixed", "局部放大", "固定放大，突出细节"], ["push", "缓慢推近", "随时间自然靠近"]].map(([H, h, x]) => /* @__PURE__ */ n.jsxs("button", { className: `caption-option ${te.mode === H ? "selected" : ""}`, role: "radio", "aria-checked": te.mode === H, onClick: () => {
      P({ mode: H }), J(H === "none" ? 0 : Math.min(te.end_frame - 1, te.start_frame + 12)), V((q) => q + 1);
    }, children: [
      /* @__PURE__ */ n.jsxs("div", { className: `frame-mode-art frame-art-${H}`, children: [
        /* @__PURE__ */ n.jsx("span", {}),
        /* @__PURE__ */ n.jsx("i", { className: `bi bi-${H === "none" ? "aspect-ratio" : H === "fixed" ? "zoom-in" : "arrows-angle-contract"}`, "aria-hidden": "true" })
      ] }),
      /* @__PURE__ */ n.jsxs("div", { className: "template-name", children: [
        /* @__PURE__ */ n.jsx("b", { children: h }),
        /* @__PURE__ */ n.jsx(en, { name: te.mode === H ? "check-circle-fill" : "circle" })
      ] }),
      /* @__PURE__ */ n.jsx("small", { children: x })
    ] }, H)) }),
    /* @__PURE__ */ n.jsxs("div", { className: "plan-note", children: [
      /* @__PURE__ */ n.jsx(en, { name: "layers" }),
      /* @__PURE__ */ n.jsxs("div", { children: [
        /* @__PURE__ */ n.jsx("b", { children: "只移动画面，文字保持清晰" }),
        /* @__PURE__ */ n.jsx("p", { children: "字幕与信息卡不跟着缩放。音乐、原声、片尾和视频时长保持。" })
      ] })
    ] }),
    /* @__PURE__ */ n.jsxs("div", { className: "sample-section", children: [
      /* @__PURE__ */ n.jsxs("div", { className: "section-title", children: [
        /* @__PURE__ */ n.jsx("h4", { children: "调整哪一段？" }),
        /* @__PURE__ */ n.jsxs("span", { children: [
          s.length,
          "段实际素材"
        ] })
      ] }),
      /* @__PURE__ */ n.jsx(nn, { rows: s, selected: W.number, label: "构图片段", onSelect: (H) => {
        y(H.number), J(0), V((h) => h + 1), Y(!1), I(!1);
      } }),
      /* @__PURE__ */ n.jsx("p", { className: "micro", children: W.spoken_text || "此段没有口播" })
    ] }),
    /* @__PURE__ */ n.jsxs("fieldset", { disabled: te.mode === "none", className: "frame-fields", children: [
      /* @__PURE__ */ n.jsxs("div", { className: "frame-zoom", children: [
        /* @__PURE__ */ n.jsxs("div", { className: "section-title", children: [
          /* @__PURE__ */ n.jsx("h4", { children: "放大多少？" }),
          /* @__PURE__ */ n.jsxs("b", { children: [
            te.zoom.toFixed(2),
            "×"
          ] })
        ] }),
        /* @__PURE__ */ n.jsx("input", { "aria-label": "画面放大程度", type: "range", min: "1", max: "1.3", step: "0.01", value: te.zoom, onChange: (H) => P({ zoom: Number(H.target.value) }) }),
        /* @__PURE__ */ n.jsxs("div", { className: "info-time-caption", children: [
          /* @__PURE__ */ n.jsx("span", { children: "1.00× · 原始大小" }),
          /* @__PURE__ */ n.jsx("span", { children: "1.30× · 轻度放大" })
        ] })
      ] }),
      /* @__PURE__ */ n.jsxs("div", { className: "frame-focus", children: [
        /* @__PURE__ */ n.jsxs("div", { className: "section-title", children: [
          /* @__PURE__ */ n.jsx("h4", { children: "聚焦哪里？" }),
          /* @__PURE__ */ n.jsx("span", { children: "手动定位，不是自动追踪" })
        ] }),
        /* @__PURE__ */ n.jsxs("button", { className: `none-button ${X ? "selected" : ""}`, "aria-pressed": X, onClick: () => {
          I(!X), Y(!1), !X && window.innerWidth <= 760 && requestAnimationFrame(() => {
            var H;
            return (H = document.querySelector(".pack-dialog .pack-body")) == null ? void 0 : H.scrollTo({ top: 0, behavior: "smooth" });
          });
        }, children: [
          " ",
          /* @__PURE__ */ n.jsx(en, { name: "crosshair" }),
          " ",
          X ? "完成定位" : "点击画面选择聚焦点"
        ] }),
        /* @__PURE__ */ n.jsxs("div", { className: "frame-presets", children: [
          [[50, 50, "居中"], [30, 45, "偏左"], [70, 45, "偏右"]].map(([H, h, x]) => /* @__PURE__ */ n.jsx("button", { className: "none-button", onClick: () => P({ x: H, y: h }), children: x }, x)),
          /* @__PURE__ */ n.jsxs("span", { className: "micro", children: [
            "横向 ",
            te.x,
            "% · 纵向 ",
            te.y,
            "%"
          ] })
        ] }),
        /* @__PURE__ */ n.jsx("p", { className: "micro", children: "放大后会裁掉部分边缘；聚焦点靠近边缘时，系统限制位移，避免黑边。" })
      ] }),
      /* @__PURE__ */ n.jsxs("div", { className: "info-timing", children: [
        /* @__PURE__ */ n.jsxs("div", { className: "section-title", children: [
          /* @__PURE__ */ n.jsx("h4", { children: "什么时候出现？" }),
          /* @__PURE__ */ n.jsxs("span", { children: [
            "片段内时间 · 总长 ",
            Vl(W.frames),
            "秒"
          ] })
        ] }),
        /* @__PURE__ */ n.jsxs("div", { className: "field-row", children: [
          /* @__PURE__ */ n.jsxs("label", { className: "field", children: [
            "开始（秒）",
            /* @__PURE__ */ n.jsx("input", { "aria-label": "构图开始秒数", type: "number", min: "0", step: "0.05", value: Vl(te.start_frame), onChange: (H) => F("start_frame", Math.round(Number(H.target.value) * 24)) })
          ] }),
          /* @__PURE__ */ n.jsxs("label", { className: "field", children: [
            "结束（秒）",
            /* @__PURE__ */ n.jsx("input", { "aria-label": "构图结束秒数", type: "number", min: "0", step: "0.05", value: Vl(te.end_frame), onChange: (H) => F("end_frame", Math.round(Number(H.target.value) * 24)) })
          ] })
        ] }),
        /* @__PURE__ */ n.jsx(_i, { label: "拖动调整构图区间", card: { ...te, id: `frame-${_}` }, cards: [], frames: W.frames, onChange: F }),
        /* @__PURE__ */ n.jsxs("div", { className: "info-time-caption", children: [
          /* @__PURE__ */ n.jsxs("span", { children: [
            "整片 ",
            Vl(W.start_frame + te.start_frame),
            "–",
            Vl(W.start_frame + te.end_frame),
            "秒"
          ] }),
          /* @__PURE__ */ n.jsxs("b", { children: [
            Vl(te.end_frame - te.start_frame),
            "秒"
          ] })
        ] }),
        /* @__PURE__ */ n.jsx("p", { className: "micro", children: te.end_frame === W.frames ? "推进到片段末尾后切镜，保留落点。" : "段内区间结束时，柔和回到原画。" })
      ] })
    ] }),
    !le && /* @__PURE__ */ n.jsx("p", { role: "alert", className: "error-text", children: "请在当前片段内选择至少1.50秒的有效区间。" }),
    /* @__PURE__ */ n.jsxs("details", { className: "scope-details frame-scope", children: [
      /* @__PURE__ */ n.jsx("summary", { children: "AI助手能怎样帮我？" }),
      /* @__PURE__ */ n.jsx("p", { children: "可根据你的目标提出片段、区间、倍率和聚焦位置建议，解释为什么使用。你查看、调整并采用后才制作；不会自行应用或重新生成画面。" }),
      /* @__PURE__ */ n.jsx("p", { children: "打开设置和手动调整不调用AI。聚焦点是固定位置，不是人物追踪；不能保证全程保留移动主体，请播放检查。" })
    ] }),
    /* @__PURE__ */ n.jsx("button", { className: "none-button frame-reset", onClick: () => {
      P(im(W)), I(!1), J(0), V((H) => H + 1);
    }, children: "还原当前片段构图" })
  ] });
  return j({ preview: ee, controls: fe, valid: le });
}
const gi = { feature: { name: "卖点卡", icon: "stars", desc: "让用途与特点更清楚" }, step: { name: "步骤提示", icon: "list-ol", desc: "把说明拆成容易跟随的步骤" }, contact: { name: "联系 / CTA", icon: "chat-dots", desc: "告诉观众下一步怎么做" } }, Jt = ({ name: s }) => /* @__PURE__ */ n.jsx("i", { className: `bi bi-${s}`, "aria-hidden": "true" }), Zt = (s) => (s / 24).toFixed(2);
function xv(s, d, b = 1) {
  return { id: crypto.randomUUID(), type: s, scene_number: d.number, start_frame: 0, end_frame: Math.min(d.frames, 72), title: s === "feature" ? "填写重点" : s === "step" ? "填写步骤" : "联系我们", body: "", step: b, channel: "none", contact: "", position: "top-left", replace_product_info: s === "feature", source: "你输入的文案" };
}
function gv(s, d) {
  const b = [];
  return s.length > 6 && b.push("首版最多准备6张信息卡。"), s.forEach((f, E) => {
    const z = d.find((O) => O.number === f.scene_number);
    f.title.trim() || b.push(`第${E + 1}张：请填写卡片标题。`), (!z || f.start_frame < 0 || f.end_frame > z.frames || f.end_frame - f.start_frame < 36) && b.push(`第${E + 1}张：出现区间须在当前片段内，且至少保留1.50秒。`);
    for (let O = 0; O < E; O++) {
      const U = s[O];
      U.scene_number === f.scene_number && Math.max(U.start_frame, f.start_frame) < Math.min(U.end_frame, f.end_frame) && b.push(`第${O + 1}张与第${E + 1}张时间重叠；请错开，避免挤在一起。`);
    }
  }), [...new Set(b)];
}
function jv({ scene: s, cards: d, settings: b, focus: f = 0, focusKey: E = 0, visible: z = !0, recipe: O = null, effect: U = null, emphasis: M = [], overlays: j = [], onTime: _ = () => {
} }) {
  const y = T.useRef(), p = T.useRef(), [Y, X] = T.useState(f), [I, ie] = T.useState(!1), [J, ue] = T.useState(!1), [V, W] = T.useState(0.25), [te, le] = T.useState(""), P = (h) => {
    var q;
    const x = Math.max(0, Math.min(s.frames - 1, h));
    X(x), _(x), ((q = y.current) == null ? void 0 : q.readyState) >= 1 && (y.current.currentTime = (s.in_frame + x) / 24);
  };
  T.useEffect(() => {
    const h = new ResizeObserver((x) => W(x[0].contentRect.width / 720));
    return h.observe(p.current), () => h.disconnect();
  }, []), T.useEffect(() => {
    y.current.pause(), ie(!1), le(""), P(f);
  }, [s.clip_id, f, E]), T.useEffect(() => {
    const h = y.current;
    let x;
    const q = () => {
      let Z = Math.round(h.currentTime * 24) - s.in_frame;
      Z >= s.frames && (h.pause(), Z = s.frames - 1), X(Math.max(0, Z)), _(Z), h.paused || (x = requestAnimationFrame(q));
    }, L = () => {
      ie(!0), x = requestAnimationFrame(q);
    }, S = () => ie(!1);
    return h.addEventListener("play", L), h.addEventListener("pause", S), () => {
      cancelAnimationFrame(x), h.pause(), h.removeEventListener("play", L), h.removeEventListener("pause", S);
    };
  }, [s.clip_id]);
  const F = ha(U, Y, s.frames), ee = z ? d.find((h) => h.scene_number === s.number && Y >= h.start_frame && Y < h.end_frame) : null, fe = O != null && O.scene ? { ...s, ...O.scene, raw_url: s.raw_url } : s, H = ee != null && ee.replace_product_info ? { ...fe, original_subtitle: fe.spoken_text } : fe;
  return /* @__PURE__ */ n.jsxs(n.Fragment, { children: [
    /* @__PURE__ */ n.jsxs("div", { className: "pack-player compact-player", children: [
      /* @__PURE__ */ n.jsxs("div", { className: "portrait-stage", ref: p, children: [
        /* @__PURE__ */ n.jsx("video", { ref: y, src: s.raw_url, style: { transformOrigin: "0 0", transform: `translate(${F.x * 100}%,${F.y * 100}%) scale(${F.scale})` }, muted: J, playsInline: !0, preload: "auto", onLoadedMetadata: () => P(f), onError: () => le("原始片段读取失败，请重新打开页面。") }),
        /* @__PURE__ */ n.jsx(an, { cards: d, overlays: j, scene: s, framing: U, effect: M.find((h) => h.scene_number === s.number), frame: Y }),
        /* @__PURE__ */ n.jsxs("div", { style: { position: "absolute", left: 0, top: 0, width: 720, height: 1280, transform: `scale(${V})`, transformOrigin: "top left", pointerEvents: "none" }, children: [
          /* @__PURE__ */ n.jsx(tn, { scene: H, settings: (O == null ? void 0 : O.settings) || b, original: (O == null ? void 0 : O.original) || !1, frame: Y }),
          /* @__PURE__ */ n.jsx(ln, { card: ee }),
          /* @__PURE__ */ n.jsx(eu, { layers: j.filter((h) => h.scene_number === s.number), frame: Y })
        ] })
      ] }),
      /* @__PURE__ */ n.jsxs("div", { className: "transport", children: [
        /* @__PURE__ */ n.jsx("button", { "aria-label": I ? "暂停信息卡预览" : "播放信息卡预览", onClick: () => {
          I ? y.current.pause() : (Y >= s.frames - 1 && P(0), y.current.play().catch(() => le("请重新点击播放。")));
        }, children: /* @__PURE__ */ n.jsx(Jt, { name: I ? "pause-fill" : "play-fill" }) }),
        /* @__PURE__ */ n.jsxs("span", { children: [
          (Y / 24).toFixed(1),
          " / ",
          (s.frames / 24).toFixed(1)
        ] }),
        /* @__PURE__ */ n.jsx("input", { type: "range", "aria-label": "信息卡预览播放进度", min: "0", max: s.frames - 1, step: "1", value: Math.min(Y, s.frames - 1), onChange: (h) => P(Number(h.target.value)) }),
        /* @__PURE__ */ n.jsx("button", { "aria-label": J ? "开启预览声音" : "静音信息卡预览", onClick: () => ue(!J), children: /* @__PURE__ */ n.jsx(Jt, { name: J ? "volume-mute" : "volume-up" }) })
      ] })
    ] }),
    te && /* @__PURE__ */ n.jsx("p", { className: "error-text", role: "alert", children: te }),
    /* @__PURE__ */ n.jsx("span", { className: "info-live-state", children: ee ? "信息卡显示中" : "当前时刻无信息卡" })
  ] });
}
function _v({ rows: s, cards: d, setCards: b, settings: f, recipes: E = [], effects: z = [], emphasis: O = [], overlays: U = [], children: M, onMessage: j }) {
  var me, w, m;
  const [_, y] = T.useState(((me = d[0]) == null ? void 0 : me.id) || null), [p, Y] = T.useState(((w = d[0]) == null ? void 0 : w.scene_number) || s[0].number), [X, I] = T.useState(!0), [ie, J] = T.useState(((m = d[0]) == null ? void 0 : m.start_frame) + 6 || 0), [ue, V] = T.useState(0), [W, te] = T.useState(!1), [le, P] = T.useState(null), F = d.find((o) => o.id === _) || null, ee = s.find((o) => o.number === ((F == null ? void 0 : F.scene_number) ?? p)) || s[0], fe = gv(d, s);
  T.useEffect(() => {
    if (_ && !d.some((o) => o.id === _)) {
      const o = d[0];
      y((o == null ? void 0 : o.id) || null), o && J(o.start_frame + 6);
    }
  }, [d]);
  const H = (o) => {
    y(o.id), Y(o.scene_number), J(o.start_frame + 6), V((D) => D + 1), I(!0);
  }, h = (o, D) => {
    b((G) => G.map((ae) => ae.id === _ ? { ...rm(ae, { [o]: D }), ...o === "type" ? { replace_product_info: D === "feature" } : {}, ...["title", "body", "contact"].includes(o) ? { source: "你输入的文案" } : {} } : ae)), o === "start_frame" && J(Math.max(0, D + 2));
  }, x = (o) => {
    if (d.length >= 6) {
      j("最多准备6张信息卡，请先删除不需要的卡片。");
      return;
    }
    let D = null;
    for (const ae of [ee, ...s.filter((ce) => ce.number !== ee.number)]) {
      const ce = d.filter((Oe) => Oe.scene_number === ae.number).sort((Oe, _e) => Oe.start_frame - _e.start_frame);
      let de = 0;
      for (const Oe of [...ce.map((_e) => _e.start_frame), ae.frames]) {
        if (Oe - de >= 36) {
          D = { s: ae, start: de, end: Math.min(Oe, de + 72) };
          break;
        }
        const _e = ce.find((nt) => nt.start_frame === Oe);
        de = (_e == null ? void 0 : _e.end_frame) ?? de;
      }
      if (D) break;
    }
    if (!D) {
      j("当前片段没有足够空闲时间，请缩短或删除已有卡片。");
      return;
    }
    const G = { ...xv(o, D.s, d.filter((ae) => ae.type === "step").length + 1), start_frame: D.start, end_frame: D.end };
    b((ae) => [...ae, G]), H(G), te(!1);
  }, q = (o) => {
    P(o), b((D) => D.filter((G) => G.id !== o.id)), j("已移除，可在信息卡列表下方撤销。");
  }, L = (o) => Array.from(o).reduce((D, G) => D + (G.charCodeAt(0) > 255 ? 1 : 0.5), 0), S = F ? Math.max(1.5, (L(F.title) + L(F.body) + L(F.contact)) / 8) : 0, Z = /* @__PURE__ */ n.jsxs(n.Fragment, { children: [
    /* @__PURE__ */ n.jsxs("div", { className: "section-top", children: [
      /* @__PURE__ */ n.jsxs("div", { children: [
        /* @__PURE__ */ n.jsx("h3", { children: "把重点，留在画面里" }),
        /* @__PURE__ */ n.jsx("p", { children: "补充关键文字，口播和原片保持。" })
      ] }),
      /* @__PURE__ */ n.jsxs("button", { className: "none-button", disabled: d.length >= 6, onClick: () => te(!W), children: [
        /* @__PURE__ */ n.jsx(Jt, { name: "plus-lg" }),
        " 添加信息卡"
      ] })
    ] }),
    /* @__PURE__ */ n.jsxs("div", { className: "info-sample-note", children: [
      /* @__PURE__ */ n.jsx(Jt, { name: "eye" }),
      " 沿用真实片段，本地合成；不会重新生成视频。"
    ] }),
    (W || d.length === 0) && /* @__PURE__ */ n.jsx("div", { className: "info-type-picker", role: "group", "aria-label": "添加信息卡类型", children: Object.entries(gi).map(([o, D]) => /* @__PURE__ */ n.jsxs("button", { className: "template-option", onClick: () => x(o), children: [
      /* @__PURE__ */ n.jsx("div", { className: `info-template-icon info-icon-${o}`, children: /* @__PURE__ */ n.jsx(Jt, { name: D.icon }) }),
      /* @__PURE__ */ n.jsx("b", { children: D.name }),
      /* @__PURE__ */ n.jsx("small", { children: D.desc })
    ] }, o)) }),
    d.length === 0 ? /* @__PURE__ */ n.jsxs("div", { className: "info-empty", children: [
      /* @__PURE__ */ n.jsx(Jt, { name: "card-heading" }),
      /* @__PURE__ */ n.jsx("b", { children: "还没有信息卡" }),
      /* @__PURE__ */ n.jsx("p", { children: "选择一种类型开始；不添加也可以保持原片。" })
    ] }) : /* @__PURE__ */ n.jsx(n.Fragment, { children: /* @__PURE__ */ n.jsx("div", { className: "info-list", role: "group", "aria-label": "信息卡列表", children: d.map((o, D) => {
      const G = s.find((ae) => ae.number === o.scene_number);
      return /* @__PURE__ */ n.jsxs("div", { className: `info-list-row ${o.id === _ ? "selected" : ""}`, children: [
        /* @__PURE__ */ n.jsxs("button", { className: "info-list-select", "aria-pressed": o.id === _, onClick: () => H(o), children: [
          /* @__PURE__ */ n.jsx("span", { className: "info-list-icon", children: /* @__PURE__ */ n.jsx(Jt, { name: gi[o.type].icon }) }),
          /* @__PURE__ */ n.jsxs("span", { children: [
            /* @__PURE__ */ n.jsx("b", { children: o.title || "未填写标题" }),
            /* @__PURE__ */ n.jsxs("small", { children: [
              gi[o.type].name,
              " · 第",
              G.number,
              "段 · 片段内 ",
              Zt(o.start_frame),
              "–",
              Zt(o.end_frame),
              "秒"
            ] })
          ] })
        ] }),
        /* @__PURE__ */ n.jsx("button", { className: "info-delete", "aria-label": `删除第${D + 1}张信息卡`, onClick: () => q(o), children: /* @__PURE__ */ n.jsx(Jt, { name: "trash3" }) })
      ] }, o.id);
    }) }) }),
    le && /* @__PURE__ */ n.jsxs("div", { className: "info-undo", children: [
      /* @__PURE__ */ n.jsxs("span", { children: [
        "已移除「",
        le.title,
        "」"
      ] }),
      /* @__PURE__ */ n.jsx("button", { className: "none-button", onClick: () => {
        d.length < 6 && (b((o) => [...o, le]), H(le), P(null));
      }, children: "撤销" })
    ] }),
    F && /* @__PURE__ */ n.jsxs(n.Fragment, { children: [
      /* @__PURE__ */ n.jsxs("div", { className: "info-edit-heading", children: [
        /* @__PURE__ */ n.jsx("b", { children: "编辑这张信息卡" }),
        /* @__PURE__ */ n.jsx("span", { className: "sample-badge", title: (F.source_refs || []).map((o) => o === "user" ? "本次用户文案" : `第${o.split(":")[1]}段分镜`).join("、"), children: F.source })
      ] }),
      /* @__PURE__ */ n.jsx("div", { className: "field-row", children: /* @__PURE__ */ n.jsxs("label", { className: "field", children: [
        "卡片类型",
        /* @__PURE__ */ n.jsx("select", { "aria-label": "卡片类型", value: F.type, onChange: (o) => h("type", o.target.value), children: Object.entries(gi).map(([o, D]) => /* @__PURE__ */ n.jsx("option", { value: o, children: D.name }, o)) })
      ] }) }),
      /* @__PURE__ */ n.jsxs("div", { className: "section-title", children: [
        /* @__PURE__ */ n.jsx("h4", { children: "出现在哪一段？" }),
        /* @__PURE__ */ n.jsxs("span", { children: [
          s.length,
          "段实际素材"
        ] })
      ] }),
      /* @__PURE__ */ n.jsx(nn, { rows: s, selected: F.scene_number, label: "信息卡所属片段", onSelect: (o) => {
        b((D) => D.map((G) => G.id === _ ? { ...G, scene_number: o.number, start_frame: 0, end_frame: Math.min(o.frames, 72) } : G)), Y(o.number), J(6), V((D) => D + 1);
      } }),
      F.type === "step" && /* @__PURE__ */ n.jsxs("label", { className: "field info-step-field", children: [
        "步骤编号",
        /* @__PURE__ */ n.jsx("select", { "aria-label": "步骤编号", value: F.step, onChange: (o) => h("step", Number(o.target.value)), children: Array.from({ length: 9 }, (o, D) => /* @__PURE__ */ n.jsxs("option", { value: D + 1, children: [
          "第 ",
          D + 1,
          " 步"
        ] }, D + 1)) })
      ] }),
      /* @__PURE__ */ n.jsxs("label", { className: "field", children: [
        "卡片标题 ",
        /* @__PURE__ */ n.jsxs("span", { className: "info-count", children: [
          F.title.length,
          "/18"
        ] }),
        /* @__PURE__ */ n.jsx("input", { "aria-label": "卡片标题", value: F.title, maxLength: "18", onChange: (o) => h("title", o.target.value) })
      ] }),
      /* @__PURE__ */ n.jsxs("label", { className: "field", children: [
        "补充说明 ",
        /* @__PURE__ */ n.jsxs("span", { className: "info-count", children: [
          "选填 · ",
          F.body.length,
          "/40"
        ] }),
        /* @__PURE__ */ n.jsx("textarea", { "aria-label": "卡片补充说明", rows: "2", value: F.body, maxLength: "40", placeholder: "一句简短说明即可", onChange: (o) => h("body", o.target.value) })
      ] }),
      F.type === "contact" && /* @__PURE__ */ n.jsxs(n.Fragment, { children: [
        /* @__PURE__ */ n.jsxs("div", { className: "field-row", children: [
          /* @__PURE__ */ n.jsxs("label", { className: "field", children: [
            "附带联系方式",
            /* @__PURE__ */ n.jsxs("select", { "aria-label": "联系渠道", value: F.channel, onChange: (o) => h("channel", o.target.value), children: [
              /* @__PURE__ */ n.jsx("option", { value: "none", children: "仅行动提示" }),
              /* @__PURE__ */ n.jsx("option", { value: "whatsapp", children: "WhatsApp" }),
              /* @__PURE__ */ n.jsx("option", { value: "phone", children: "电话" })
            ] })
          ] }),
          F.channel !== "none" && /* @__PURE__ */ n.jsxs("label", { className: "field", children: [
            "联系号码 · 选填",
            /* @__PURE__ */ n.jsx("input", { "aria-label": "联系号码", value: F.contact, maxLength: "40", placeholder: "输入你确认的公司号码", onChange: (o) => h("contact", o.target.value) })
          ] })
        ] }),
        /* @__PURE__ */ n.jsx("p", { className: "micro", children: F.channel !== "none" && !F.contact.trim() ? "未填写号码，将只显示行动提示，不使用示例号码。" : "视频中的联系方式是文字展示，不是可点击链接。" })
      ] }),
      /* @__PURE__ */ n.jsxs("div", { className: "info-timing", children: [
        /* @__PURE__ */ n.jsxs("div", { className: "section-title", children: [
          /* @__PURE__ */ n.jsx("h4", { children: "什么时候出现？" }),
          /* @__PURE__ */ n.jsxs("span", { children: [
            "片段内时间 · 总长 ",
            Zt(ee.frames),
            "秒"
          ] })
        ] }),
        /* @__PURE__ */ n.jsxs("div", { className: "field-row", children: [
          /* @__PURE__ */ n.jsxs("label", { className: "field", children: [
            "开始（秒）",
            /* @__PURE__ */ n.jsx("input", { "aria-label": "信息卡开始秒数", type: "number", min: "0", max: Zt(ee.frames), step: "0.05", value: Zt(F.start_frame), onChange: (o) => h("start_frame", Math.round(Number(o.target.value) * 24)) })
          ] }),
          /* @__PURE__ */ n.jsxs("label", { className: "field", children: [
            "结束（秒）",
            /* @__PURE__ */ n.jsx("input", { "aria-label": "信息卡结束秒数", type: "number", min: "0", max: Zt(ee.frames), step: "0.05", value: Zt(F.end_frame), onChange: (o) => h("end_frame", Math.round(Number(o.target.value) * 24)) })
          ] })
        ] }),
        /* @__PURE__ */ n.jsx(_i, { card: F, cards: d, frames: ee.frames, onChange: (o, D) => {
          h(o, D), J(o === "end_frame" ? D - 1 : D), V((G) => G + 1), I(!0);
        } }),
        /* @__PURE__ */ n.jsxs("div", { className: "info-time-caption", children: [
          /* @__PURE__ */ n.jsxs("span", { children: [
            "整片 ",
            Zt(ee.start_frame + F.start_frame),
            "–",
            Zt(ee.start_frame + F.end_frame),
            "秒"
          ] }),
          /* @__PURE__ */ n.jsxs("b", { children: [
            "显示 ",
            Zt(F.end_frame - F.start_frame),
            "秒"
          ] })
        ] }),
        S > (F.end_frame - F.start_frame) / 24 && /* @__PURE__ */ n.jsxs("p", { className: "info-warning", children: [
          "文字较多，建议精简或延长，估计至少需要 ",
          S.toFixed(1),
          " 秒阅读。"
        ] })
      ] }),
      /* @__PURE__ */ n.jsxs("div", { className: "field-row", children: [
        /* @__PURE__ */ n.jsxs("label", { className: "field", children: [
          "显示位置",
          /* @__PURE__ */ n.jsxs("select", { "aria-label": "信息卡位置", value: F.position, onChange: (o) => h("position", o.target.value), children: [
            /* @__PURE__ */ n.jsx("option", { value: "top-left", children: "左上方" }),
            /* @__PURE__ */ n.jsx("option", { value: "top-center", children: "上方居中" }),
            /* @__PURE__ */ n.jsx("option", { value: "top-right", children: "右上方" })
          ] })
        ] }),
        /* @__PURE__ */ n.jsxs("div", { className: "info-placement-note", children: [
          /* @__PURE__ */ n.jsx(Jt, { name: "bounding-box" }),
          /* @__PURE__ */ n.jsxs("span", { children: [
            "字幕区域独立保留",
            /* @__PURE__ */ n.jsx("br", {}),
            /* @__PURE__ */ n.jsx("small", { children: "位置为预设，需播放确认遮挡" })
          ] })
        ] })
      ] }),
      F.type === "feature" && /* @__PURE__ */ n.jsxs("label", { className: "audio-toggle", children: [
        /* @__PURE__ */ n.jsxs("span", { children: [
          /* @__PURE__ */ n.jsx("b", { children: "合并原有商品说明" }),
          /* @__PURE__ */ n.jsx("small", { children: "卡片显示时避免同义文字重复；隐藏后可恢复。" })
        ] }),
        /* @__PURE__ */ n.jsx("input", { "aria-label": "合并原有商品说明", type: "checkbox", checked: F.replace_product_info, onChange: (o) => h("replace_product_info", o.target.checked) })
      ] }),
      /* @__PURE__ */ n.jsxs("div", { className: "plan-note", children: [
        /* @__PURE__ */ n.jsx(Jt, { name: "shield-check" }),
        /* @__PURE__ */ n.jsxs("div", { children: [
          /* @__PURE__ */ n.jsx("b", { children: "真实资料，由你确认" }),
          /* @__PURE__ */ n.jsx("p", { children: "沿用已保存分镜或你的输入。号码按你的输入呈现；位置请播放确认遮挡。" })
        ] })
      ] })
    ] }),
    fe.length > 0 && /* @__PURE__ */ n.jsx("div", { className: "info-errors", role: "alert", children: fe.map((o) => /* @__PURE__ */ n.jsx("p", { children: o }, o)) })
  ] }), ne = /* @__PURE__ */ n.jsxs(n.Fragment, { children: [
    /* @__PURE__ */ n.jsxs("div", { className: "eyebrow", children: [
      "边看边调整 ",
      /* @__PURE__ */ n.jsx("span", { children: "当前片段预览" })
    ] }),
    /* @__PURE__ */ n.jsx(jv, { scene: ee, cards: d, settings: f, focus: ie, focusKey: ue, visible: X, recipe: E.find((o) => o.scene.number === ee.number), emphasis: O, overlays: U, effect: z.find((o) => o.scene_number === ee.number) }),
    /* @__PURE__ */ n.jsxs("div", { className: "preview-shortcuts", children: [
      /* @__PURE__ */ n.jsx("button", { className: "none-button", onClick: () => {
        J(F ? F.start_frame + 2 : 0), V((o) => o + 1), I(!0);
      }, children: "重看出现位置" }),
      /* @__PURE__ */ n.jsx("button", { className: "none-button", onClick: () => I(!X), children: X ? "隐藏信息卡" : "显示信息卡" })
    ] }),
    /* @__PURE__ */ n.jsxs("div", { className: "length-note", children: [
      /* @__PURE__ */ n.jsx(Jt, { name: "clock" }),
      /* @__PURE__ */ n.jsxs("div", { children: [
        /* @__PURE__ */ n.jsxs("b", { children: [
          "第",
          ee.number,
          "段 · ",
          Zt(ee.start_frame),
          "–",
          Zt(ee.end_frame),
          "秒"
        ] }),
        /* @__PURE__ */ n.jsx("strong", { children: "整片时长不变" })
      ] })
    ] }),
    /* @__PURE__ */ n.jsx("p", { className: "preview-footnote", children: "预览当前真实原声片段；采用后统一合成；保留原声、动态字幕、音乐与品牌片尾。" })
  ] });
  return M({ preview: ne, controls: Z, valid: fe.length === 0 });
}
const dt = document.querySelector("[data-film-packaging]");
async function ji(s, d) {
  const b = await fetch(s, { method: d ? "POST" : "GET", headers: { Accept: "application/json", ...d ? { "Content-Type": "application/json", "X-CSRF-TOKEN": dt.dataset.csrf } : {} }, ...d ? { body: JSON.stringify(d) } : {} }), f = await b.json();
  if (!b.ok) throw Error(Object.values(f.errors || {}).flat()[0] || f.message || "包装设置未能读取");
  return f;
}
function Sv() {
  const [s, d] = T.useState(null), [b, f] = T.useState(!1), [E, z] = T.useState(""), [O, U] = T.useState(!1), [M, j] = T.useState(!1), [_, y] = T.useState(!1), [p, Y] = T.useState(null), [X, I] = T.useState(!1), [ie, J] = T.useState("subtitles"), [ue, V] = T.useState([]), [W, te] = T.useState(""), [le, P] = T.useState([]), [F, ee] = T.useState(!1), [fe, H] = T.useState([]), [h, x] = T.useState(!1), [q, L] = T.useState({ layers: [], selected_id: null }), [S, Z] = T.useState(!1), [ne, me] = T.useState(null), [w, m] = T.useState(!1);
  T.useEffect(() => {
    if (!W) return;
    const ve = setTimeout(() => te(""), 3500);
    return () => clearTimeout(ve);
  }, [W]);
  const o = T.useRef(null), D = dt.dataset.csrf, G = T.useRef(null), ae = T.useRef(!0);
  G.current || (G.current = pv(dt.dataset.config)), T.useEffect(() => () => {
    ae.current = !1;
  }, []);
  const ce = async (ve = null, ge = null) => {
    var qe;
    j(!0), z(""), te("");
    try {
      const ye = await G.current.get();
      let pe = "subtitles";
      if (ve === "packaging") {
        pe = ge && (ge.track !== ye.settings.track || ge.volume !== ye.settings.volume) ? "music" : "brand";
        const Ve = ["enabled", "template", "duration", "company", "cta", "track", "volume", "duck", "fade"];
        for (const Re of Ve) Object.hasOwn(ge || {}, Re) && (ye.settings[Re] = ge[Re]);
      }
      me((ve === "speed" ? ge : ye.speed) || { scene_number: ye.subtitles.scenes[0].number, start_frame: 0, end_frame: Math.min(48, ye.subtitles.scenes[0].frames), rate: 1, protect: !0 }), m(ve === "speed"), ve === "speed" && (pe = "speed"), ye.csrf = D, ye.addTrack = (Ve) => {
        G.current.addTrack(Ve), d((Re) => ({ ...Re, tracks: [Ve, ...Re.tracks.filter((Dt) => Dt.id !== Ve.id)] }));
      }, ye.addOverlayImage = (Ve) => {
        G.current.addImage(Ve), d((Re) => ({ ...Re, overlay_images: [Ve, ...Re.overlay_images.filter((Dt) => Dt.id !== Ve.id)] }));
      }, ve === "overlays" && (pe = "overlay");
      const vt = (ve === "overlays" ? ge : ye.overlays || []).map((Ve) => {
        const Re = ye.overlay_images.find((Dt) => Dt.id === Ve.image_id);
        return { ...Ve, url: (Re == null ? void 0 : Re.url) || "", name: (Re == null ? void 0 : Re.name) || "图片不可用" };
      });
      L({ layers: vt, selected_id: (qe = vt[0]) == null ? void 0 : qe.id }), Z(ve === "overlays"), ve === "emphasis" && (pe = "emphasis"), H(ve === "emphasis" ? ge : ye.emphasis || []), x(ve === "emphasis"), ve === "framing" && (pe = "framing"), P(ve === "framing" ? ge : ye.framing || []), ee(ve === "framing"), ve === "information" && (pe = "information"), V(ve === "information" ? ge : ye.information_cards || []), d(ye), Y({ ...ye.subtitles.settings, ...ve === "subtitles" ? ge : {} }), I(ve === "subtitles"), J(pe), o.current = null, f(!0), document.querySelectorAll(".ws-finished-player video").forEach((Ve) => Ve.pause());
    } catch (ye) {
      z(ye.message);
    } finally {
      j(!1);
    }
  };
  T.useEffect(() => {
    const ve = ["packaging", "subtitles", "information", "framing", "emphasis", "overlays", "speed"].map((ge) => {
      const qe = (pe) => {
        var vt;
        if (((vt = pe.detail) == null ? void 0 : vt.base_film_id) !== dt.dataset.film) {
          z("请先切回建议对应的成片版本。");
          return;
        }
        ce(ge, pe.detail.settings);
      }, ye = ge === "speed" ? "marketing-speed-proposal" : ge === "overlays" ? "marketing-overlays-proposal" : ge === "emphasis" ? "marketing-emphasis-proposal" : ge === "framing" ? "marketing-framing-proposal" : ge === "information" ? "marketing-information-proposal" : ge === "subtitles" ? "marketing-subtitle-proposal" : "marketing-packaging-proposal";
      return document.addEventListener(ye, qe), [ye, qe];
    });
    return () => ve.forEach(([ge, qe]) => document.removeEventListener(ge, qe));
  }, []);
  const de = async (ve) => {
    const ge = await ji(ve);
    ae.current && (G.current.updateSubtitles(ge), d((qe) => ({ ...qe, subtitles: ge })));
  };
  T.useEffect(() => {
    if (!b || !["queued", "running"].includes(s == null ? void 0 : s.subtitles.alignment.status)) return;
    let ve = !1, ge = !1;
    const qe = setInterval(async () => {
      if (!ge) {
        ge = !0;
        try {
          const ye = await ji(s.subtitles.config_url);
          ve || (G.current.updateSubtitles(ye), d((pe) => ({ ...pe, subtitles: ye })));
        } catch (ye) {
          ve || z(ye.message);
        } finally {
          ge = !1;
        }
      }
    }, 4e3);
    return () => {
      ve = !0, clearInterval(qe);
    };
  }, [b, s == null ? void 0 : s.subtitles.alignment.status]);
  const Oe = async () => {
    y(!0), z("");
    try {
      await ji(s.subtitles.prepare_url, {}), await de(s.subtitles.config_url);
    } catch (ve) {
      z(ve.message);
    } finally {
      y(!1);
    }
  }, _e = async (ve) => {
    var ge;
    if (!O) {
      if (X && s.subtitles.alignment.status !== "ready") {
        z("逐词同步尚未完成，所选样式已保留；完成后再生成预览。");
        return;
      }
      U(!0), z("");
      try {
        const qe = { base_sha256: s.hash, settings: ve, consent: !0, information_cards: ue, ...w ? { speed: (ne == null ? void 0 : ne.rate) === 1 ? null : ne } : {}, ...F ? { framing: le } : {}, ...h ? { emphasis: fe } : {}, ...S ? { overlays: q.layers.map(({ url: vt, name: Ve, ...Re }) => Re) } : {}, ...X ? { subtitle_settings: p } : {} }, ye = JSON.stringify(qe);
        ((ge = o.current) == null ? void 0 : ge.key) !== ye && (o.current = { id: crypto.randomUUID(), key: ye });
        const pe = await ji(s.apply_url, { ...qe, request_id: o.current.id });
        location.assign(pe.url);
      } catch (qe) {
        z(qe.message), U(!1);
      }
    }
  }, nt = ((s == null ? void 0 : s.visual_recipe) || []).map((ve) => X && (p.scope === "all" || p.scene === ve.scene.number) && ve.scene.spoken_text ? { scene: (s.subtitles.alignment.scenes || s.subtitles.scenes).find((ge) => ge.number === ve.scene.number), settings: { ...p, scope: "all" }, original: !1 } : ve);
  return /* @__PURE__ */ n.jsxs(n.Fragment, { children: [
    W && /* @__PURE__ */ n.jsx("div", { className: "sample-toast", role: "status", children: W }),
    /* @__PURE__ */ n.jsxs("div", { className: "ws-packaging", children: [
      /* @__PURE__ */ n.jsxs("div", { className: "pack-entry", children: [
        /* @__PURE__ */ n.jsxs("div", { children: [
          /* @__PURE__ */ n.jsxs("b", { children: [
            /* @__PURE__ */ n.jsx("i", { className: "bi bi-sliders", "aria-hidden": "true" }),
            " 包装设置"
          ] }),
          /* @__PURE__ */ n.jsxs("small", { children: [
            dt.dataset.subtitleSummary || "原有字幕",
            Number(dt.dataset.overlayCount) > 0 ? ` · ${dt.dataset.overlayCount}张叠加图片` : "",
            Number(dt.dataset.emphasisCount) > 0 ? ` · ${dt.dataset.emphasisCount}段强调` : "",
            Number(dt.dataset.framingCount) > 0 ? ` · ${dt.dataset.framingCount}段构图` : "",
            Number(dt.dataset.informationCount) > 0 ? ` · ${dt.dataset.informationCount}张信息卡` : "",
            " · ",
            dt.dataset.summary || "字幕、品牌片尾与背景音乐"
          ] })
        ] }),
        /* @__PURE__ */ n.jsxs("button", { type: "button", className: "outline-button", disabled: M, onClick: () => ce(), children: [
          M ? "读取设置…" : "包装设置",
          " ",
          /* @__PURE__ */ n.jsx("i", { className: "bi bi-arrow-right", "aria-hidden": "true" })
        ] })
      ] }),
      E && !b && /* @__PURE__ */ n.jsx("p", { role: "alert", className: "error-text", children: E })
    ] }),
    b && s && p && dv.createPortal(/* @__PURE__ */ n.jsx("div", { className: "ws-packaging", children: /* @__PURE__ */ n.jsx(tu.Provider, { value: s, children: /* @__PURE__ */ n.jsx(av, { visual: { framing: le, emphasis: fe, cards: ue, settings: p, recipes: nt, overlays: q.layers }, rows: s.subtitles.alignment.scenes || s.subtitles.scenes, draft: ne, setDraft: me, onTouch: () => m(!0), totalDuration: s.source_duration + (s.settings.enabled ? s.settings.duration : 0), outroDuration: s.settings.enabled ? s.settings.duration : 0, children: (ve) => /* @__PURE__ */ n.jsx(vv, { overlays: q.layers, emphasis: fe, effects: le, cards: ue, recipes: nt, data: s.subtitles, draft: p, setDraft: Y, onTouch: () => I(!0), onPrepare: Oe, busy: O || _, error: "", children: (ge) => /* @__PURE__ */ n.jsx(_v, { overlays: q.layers, emphasis: fe, effects: le, rows: s.subtitles.alignment.scenes || s.subtitles.scenes, cards: ue, setCards: V, settings: p, recipes: (s.visual_recipe || []).map((qe) => X && (p.scope === "all" || p.scene === qe.scene.number) && qe.scene.spoken_text ? { scene: (s.subtitles.alignment.scenes || s.subtitles.scenes).find((ye) => ye.number === qe.scene.number), settings: { ...p, scope: "all" }, original: !1 } : qe), onMessage: te, children: (qe) => /* @__PURE__ */ n.jsx(yv, { overlays: q.layers, emphasis: fe, rows: s.subtitles.alignment.scenes || s.subtitles.scenes, effects: le, setEffects: P, cards: ue, settings: p, recipes: nt, onTouch: () => ee(!0), children: (ye) => /* @__PURE__ */ n.jsx(ov, { overlays: q.layers, rows: s.subtitles.alignment.scenes || s.subtitles.scenes, effects: fe, setEffects: H, onTouch: () => x(!0), cards: ue, settings: p, framing: le, recipes: nt, children: (pe) => /* @__PURE__ */ n.jsx(fv, { rows: s.subtitles.alignment.scenes || s.subtitles.scenes, draft: q, setDraft: L, onTouch: () => Z(!0), visual: { framing: le, emphasis: fe, cards: ue, settings: p, recipes: nt, comparisonUrl: s.subtitles.comparison_url }, children: (vt) => /* @__PURE__ */ n.jsx(cv, { initial: s.settings, initialTab: ie, onClose: () => {
      O || (f(!1), z(""));
    }, onApply: _e, working: O || _, errorMessage: E, subtitleUI: ge, subtitleChanged: X, informationUI: qe, framingUI: ye, emphasisUI: pe, overlayUI: vt, speedUI: ve, speedChanged: w }) }) }) }) }) }) }) }) }), document.body)
  ] });
}
dt && cm.createRoot(dt).render(/* @__PURE__ */ n.jsx(Sv, {}));
Q0();
