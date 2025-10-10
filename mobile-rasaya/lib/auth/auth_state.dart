class AuthState {
  final bool loading;
  final String? token;
  final Map<String, dynamic>? me;
  final String? error;
  const AuthState({this.loading = false, this.token, this.me, this.error});

  AuthState copy(
          {bool? loading,
          String? token,
          Map<String, dynamic>? me,
          String? error}) =>
      AuthState(
          loading: loading ?? this.loading,
          token: token ?? this.token,
          me: me ?? this.me,
          error: error);
}
