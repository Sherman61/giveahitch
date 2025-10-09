const subscribers = new Set();

const normaliseError = (input) => {
  if (input instanceof Error) return input;
  if (input && typeof input === 'object' && 'message' in input) {
    const err = new Error(String(input.message || ''));
    if ('stack' in input) err.stack = String(input.stack);
    err.name = input.name ? String(input.name) : err.name;
    return err;
  }
  return new Error(typeof input === 'string' ? input : JSON.stringify(input, null, 2));
};

const emit = (payload) => {
  subscribers.forEach((fn) => {
    try { fn(payload); } catch (err) {
      console.warn('[logger] subscriber failed', err);
    }
  });
};

const baseLog = (level, context, detail, meta = {}) => {
  const timestamp = new Date().toISOString();
  const payload = { level, context, timestamp, meta };

  if (level === 'error' || level === 'warn') {
    const err = normaliseError(detail);
    payload.error = {
      message: err.message,
      name: err.name,
      stack: err.stack,
    };
    const handler = console[level] || console.error;
    handler.call(console, `[${context}]`, err, meta);
  } else {
    payload.message = typeof detail === 'string' ? detail : JSON.stringify(detail);
    (console[level] || console.log).call(console, `[${context}]`, payload.message, meta);
  }

  emit(payload);
  return payload;
};

export const logError = (context, error, meta) => baseLog('error', context, error, meta);
export const logWarn  = (context, error, meta) => baseLog('warn', context, error, meta);
export const logInfo  = (context, message, meta) => baseLog('info', context, message, meta);

export const subscribe = (fn) => {
  if (typeof fn !== 'function') return () => {};
  subscribers.add(fn);
  return () => subscribers.delete(fn);
};

const logger = {
  error: logError,
  warn: logWarn,
  info: logInfo,
  subscribe,
};

if (typeof window !== 'undefined') {
  window.AppLogger = logger;
}

export default logger;
