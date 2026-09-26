/**
 * サーバ (Inertia) から渡ってくる値の型。
 *
 * PHP 側の各コントローラが組み立てる配列と一対一で対応させる。
 * ここを直したら対応するコントローラも直すこと。
 *
 * 型はモジュールごとのファイルに書き、ここは束ねるだけにする。
 */

import './inertia';

export * from './account';
export * from './admin';
export * from './audit';
export * from './client';
export * from './credential';
export * from './device';
