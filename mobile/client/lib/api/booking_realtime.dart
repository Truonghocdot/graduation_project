import 'package:socket_io_client/socket_io_client.dart' as io;

class BookingRealtime {
  io.Socket? _socket;
  String? _requestId;
  void Function(Map<String, dynamic> event)? eventHandler;

  void connect({
    required String url,
    required String token,
    required void Function() onChange,
  }) {
    dispose();
    final socket = io.io(
      url,
      io.OptionBuilder()
          .setTransports(['websocket'])
          .setAuth({'token': token})
          .enableForceNew()
          .disableAutoConnect()
          .build(),
    );
    _socket = socket;
    socket.onConnect((_) {
      if (_requestId case final requestId?) {
        socket.emit('booking:join', requestId);
      }
      onChange();
    });
    socket.on('booking:event', (event) {
      if (event is Map) {
        eventHandler?.call(event.cast<String, dynamic>());
      }
      onChange();
    });
    socket.on('notification:event', (_) => onChange());
    socket.connect();
  }

  void watch(String? requestId) {
    final socket = _socket;
    if (_requestId case final oldId?) {
      socket?.emit('booking:leave', oldId);
    }
    _requestId = requestId;
    if (requestId != null && socket?.connected == true) {
      socket!.emit('booking:join', requestId);
    }
  }

  void dispose() {
    _socket?.dispose();
    _socket = null;
    _requestId = null;
    eventHandler = null;
  }
}
