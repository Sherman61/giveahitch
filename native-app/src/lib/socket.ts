import { apiClient } from '@/api/client';
import type {
  ChatConnectionState,
  ChatConnectionStatus,
  ChatSocketEventMap,
  DmDeletePayload,
  DmNewPayload,
  DmPresencePayload,
  DmReadPayload,
  DmTypingPayload,
  SocketAuthAck,
  WsAuthResponse,
} from '@/types/chat';
import { io, type Socket } from 'socket.io-client';

type Listener<T> = (payload: T) => void;

class ChatSocketManager {
  private socket: Socket | null = null;
  private wsUrl: string | null = null;
  private sessionEnabled = false;
  private authenticated = false;
  private presenceSnapshotLoaded = false;
  private authRequest: Promise<WsAuthResponse> | null = null;
  private reconnectTimer: ReturnType<typeof setTimeout> | null = null;
  private listeners: {
    [K in keyof ChatSocketEventMap]: Set<Listener<ChatSocketEventMap[K]>>;
  } = {
    connection: new Set(),
    'dm:new': new Set(),
    'dm:typing': new Set(),
    'dm:presence': new Set(),
    'dm:read': new Set(),
    'dm:delete': new Set(),
  };

  subscribe<K extends keyof ChatSocketEventMap>(event: K, listener: Listener<ChatSocketEventMap[K]>) {
    this.listeners[event].add(listener);
    return () => {
      this.listeners[event].delete(listener);
    };
  }

  connect() {
    this.sessionEnabled = true;
    this.clearReconnectTimer();
    if (this.socket) {
      if (!this.socket.connected) {
        this.setConnectionState('connecting');
        this.socket.connect();
      } else if (!this.authenticated) {
        this.setConnectionState('connecting');
        void this.authenticate(this.socket, true);
      }
      return;
    }

    void this.initializeSocket();
  }

  disconnect() {
    this.sessionEnabled = false;
    this.authenticated = false;
    this.presenceSnapshotLoaded = false;
    this.authRequest = null;
    this.clearReconnectTimer();
    if (this.socket) {
      this.socket.removeAllListeners();
      this.socket.disconnect();
      this.socket = null;
    }
    this.wsUrl = null;
    this.emit('connection', { state: 'idle', authenticated: false, presenceSnapshotLoaded: false });
  }

  emitTyping(recipientId: number, threadId: number | null, typing: boolean) {
    if (!this.socket || !this.authenticated || !recipientId) {
      return false;
    }

    this.socket.emit('dm:typing', {
      recipient_id: recipientId,
      thread_id: threadId,
      typing,
    });

    return true;
  }

  private async initializeSocket() {
    this.setConnectionState('connecting');

    try {
      const auth = await this.fetchWsAuth(true);
      if (!this.sessionEnabled) {
        return;
      }

      this.wsUrl = auth.ws_url;
      const socket = io(auth.ws_url, {
        path: '/socket.io',
        transports: ['websocket', 'polling'],
        autoConnect: false,
        reconnection: true,
      });

      this.bindSocket(socket);
      this.socket = socket;
      socket.connect();
    } catch (error) {
      this.handleConnectionError(error);
    }
  }

  private bindSocket(socket: Socket) {
    socket.on('connect', () => {
      this.setConnectionState('connecting');
      void this.authenticate(socket, true);
    });

    socket.on('connect_error', (error: Error) => {
      this.handleConnectionError(error);
    });

    socket.on('disconnect', () => {
      this.authenticated = false;
      this.presenceSnapshotLoaded = false;
      this.emit('connection', {
        state: this.sessionEnabled ? 'disconnected' : 'idle',
        authenticated: false,
        presenceSnapshotLoaded: false,
      });
      if (this.sessionEnabled) {
        this.scheduleReconnect();
      }
    });

    socket.on('dm:new', (payload: DmNewPayload) => {
      this.emit('dm:new', payload);
    });
    socket.on('dm:typing', (payload: DmTypingPayload) => {
      this.emit('dm:typing', payload);
    });
    socket.on('dm:presence', (payload: DmPresencePayload) => {
      this.emit('dm:presence', payload);
    });
    socket.on('dm:read', (payload: DmReadPayload) => {
      this.emit('dm:read', payload);
    });
    socket.on('dm:delete', (payload: DmDeletePayload) => {
      this.emit('dm:delete', payload);
    });
  }

  private async authenticate(socket: Socket, allowRefresh: boolean) {
    try {
      const auth = await this.fetchWsAuth(true);
      if (!this.sessionEnabled || socket !== this.socket) {
        return;
      }

      if (this.wsUrl && auth.ws_url !== this.wsUrl) {
        this.socket?.removeAllListeners();
        this.socket?.disconnect();
        this.socket = null;
        this.wsUrl = auth.ws_url;
        void this.initializeSocket();
        return;
      }

      const ack = await new Promise<SocketAuthAck>((resolve) => {
        socket.emit('auth', auth.ws_auth, (response: SocketAuthAck) => resolve(response));
      });

      if (ack?.ok) {
        this.authenticated = true;
        this.presenceSnapshotLoaded = true;
        console.log('[messages-debug] socket-auth-ack', {
          userId: ack.userId ?? null,
          rooms: ack.rooms ?? [],
          onlineUserIds: ack.online_user_ids ?? [],
        });
        const onlineUserIds = Array.isArray(ack.online_user_ids) ? ack.online_user_ids : [];
        onlineUserIds.forEach((id) => {
          const numericId = Number(id);
          if (!Number.isFinite(numericId) || numericId <= 0) {
            return;
          }

          this.emit('dm:presence', {
            user_id: numericId,
            online: true,
          });
        });
        this.clearReconnectTimer();
        this.emit('connection', {
          state: 'connected',
          authenticated: true,
          presenceSnapshotLoaded: true,
        });
        return;
      }

      if (allowRefresh) {
        this.authRequest = null;
        await this.authenticate(socket, false);
        return;
      }

      this.handleConnectionError(new Error('Socket authentication failed.'));
    } catch (error) {
      this.handleConnectionError(error);
    }
  }

  private async fetchWsAuth(forceRefresh = false) {
    if (!forceRefresh && this.authRequest) {
      return this.authRequest;
    }

    this.authRequest = apiClient.get<WsAuthResponse>('/ws_auth.php');
    try {
      return await this.authRequest;
    } finally {
      if (forceRefresh) {
        this.authRequest = null;
      }
    }
  }

  private setConnectionState(state: ChatConnectionState) {
    this.emit('connection', {
      state,
      authenticated: this.authenticated,
      presenceSnapshotLoaded: this.presenceSnapshotLoaded,
    });
  }

  private handleConnectionError(error: unknown) {
    this.authenticated = false;
    const message = error instanceof Error ? error.message : 'Socket connection failed.';
    const status: ChatConnectionStatus = {
      state: 'error',
      authenticated: false,
      presenceSnapshotLoaded: this.presenceSnapshotLoaded,
      error: message,
    };
    this.emit('connection', status);
    this.scheduleReconnect();
  }

  private scheduleReconnect() {
    if (!this.sessionEnabled || this.reconnectTimer) {
      return;
    }

    this.reconnectTimer = setTimeout(() => {
      this.reconnectTimer = null;

      if (!this.sessionEnabled) {
        return;
      }

      if (this.socket) {
        if (this.socket.connected) {
          this.setConnectionState('connecting');
          void this.authenticate(this.socket, true);
          return;
        }

        this.setConnectionState('connecting');
        this.socket.connect();
        return;
      }

      void this.initializeSocket();
    }, 2000);
  }

  private clearReconnectTimer() {
    if (!this.reconnectTimer) {
      return;
    }

    clearTimeout(this.reconnectTimer);
    this.reconnectTimer = null;
  }

  private emit<K extends keyof ChatSocketEventMap>(event: K, payload: ChatSocketEventMap[K]) {
    this.listeners[event].forEach((listener) => {
      listener(payload);
    });
  }
}

const chatSocketManager = new ChatSocketManager();

export function getChatSocketManager() {
  return chatSocketManager;
}
