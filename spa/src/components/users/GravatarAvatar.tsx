import { useEffect, useState } from "react";

interface GravatarAvatarProps {
	email: string;
	size?: number;
	className?: string;
}

/**
 * Gravatar swatch keyed on the lowercased, trimmed email's MD5. Mirrors the
 * PHP admin's `BigTree::gravatar()` helper. Falls back to a colored disc with
 * initials if the email is empty or no Gravatar is found.
 */
export const GravatarAvatar = ({ email, size = 56, className }: GravatarAvatarProps) => {
	const [hash, setHash] = useState<string | null>(null);

	useEffect(() => {
		let cancelled = false;

		md5(email.trim().toLowerCase()).then((h) => {
			if (!cancelled) {
				setHash(h);
			}
		});

		return () => {
			cancelled = true;
		};
	}, [email]);

	if (!email || !hash) {
		return (
			<div
				className={`rounded-full bg-surface-3 ${className ?? ""}`}
				style={{ width: size, height: size }}
				aria-hidden="true"
			/>
		);
	}

	const src = `https://www.gravatar.com/avatar/${hash}?s=${size * 2}&d=mp&rating=pg`;

	return (
		<img
			src={src}
			width={size}
			height={size}
			alt=""
			loading="lazy"
			className={`rounded-full bg-surface-3 ${className ?? ""}`}
			style={{ width: size, height: size }}
		/>
	);
};

/**
 * Subtle-crypto MD5 isn't available — but Gravatar still uses MD5 hashes.
 * We ship a tiny RFC 1321 implementation. The hash is *not* used as a
 * cryptographic primitive here, so the lack of a constant-time impl is fine.
 */
async function md5(input: string): Promise<string> {
	// Use the WebCrypto API where possible (MD5 is not supported), so we fall
	// back to a small in-process impl. Async signature is preserved for future
	// swap-outs.
	return md5Hex(input);
}

const md5Hex = (s: string): string => {
	const bytes = new TextEncoder().encode(s);
	const a32 = bytesToWords(bytes, s.length);
	const digest = md5Words(a32, s.length * 8);

	return wordsToHex(digest);
};

const bytesToWords = (bytes: Uint8Array, byteLen: number): number[] => {
	const words: number[] = [];

	for (let i = 0; i < byteLen; i++) {
		const idx = i >> 2;
		const current = words[idx] ?? 0;
		const byte = bytes[i] ?? 0;
		words[idx] = current | (byte << ((i % 4) * 8));
	}

	return words;
};

const wordsToHex = (words: number[]): string => {
	const hexChars = "0123456789abcdef";
	let out = "";

	for (let i = 0; i < words.length * 4; i++) {
		const word = words[i >> 2] ?? 0;
		const byte = (word >>> ((i % 4) * 8)) & 0xff;
		const hi = hexChars[(byte >>> 4) & 0x0f] ?? "0";
		const lo = hexChars[byte & 0x0f] ?? "0";
		out += hi + lo;
	}

	return out;
};

const md5Words = (x: number[], bitLen: number): number[] => {
	x[bitLen >> 5] = (x[bitLen >> 5] ?? 0) | (0x80 << (bitLen % 32));
	x[(((bitLen + 64) >>> 9) << 4) + 14] = bitLen;

	let a = 1732584193;
	let b = -271733879;
	let c = -1732584194;
	let d = 271733878;

	for (let i = 0; i < x.length; i += 16) {
		const olda = a;
		const oldb = b;
		const oldc = c;
		const oldd = d;

		a = ff(a, b, c, d, x[i + 0] ?? 0, 7, -680876936);
		d = ff(d, a, b, c, x[i + 1] ?? 0, 12, -389564586);
		c = ff(c, d, a, b, x[i + 2] ?? 0, 17, 606105819);
		b = ff(b, c, d, a, x[i + 3] ?? 0, 22, -1044525330);
		a = ff(a, b, c, d, x[i + 4] ?? 0, 7, -176418897);
		d = ff(d, a, b, c, x[i + 5] ?? 0, 12, 1200080426);
		c = ff(c, d, a, b, x[i + 6] ?? 0, 17, -1473231341);
		b = ff(b, c, d, a, x[i + 7] ?? 0, 22, -45705983);
		a = ff(a, b, c, d, x[i + 8] ?? 0, 7, 1770035416);
		d = ff(d, a, b, c, x[i + 9] ?? 0, 12, -1958414417);
		c = ff(c, d, a, b, x[i + 10] ?? 0, 17, -42063);
		b = ff(b, c, d, a, x[i + 11] ?? 0, 22, -1990404162);
		a = ff(a, b, c, d, x[i + 12] ?? 0, 7, 1804603682);
		d = ff(d, a, b, c, x[i + 13] ?? 0, 12, -40341101);
		c = ff(c, d, a, b, x[i + 14] ?? 0, 17, -1502002290);
		b = ff(b, c, d, a, x[i + 15] ?? 0, 22, 1236535329);

		a = gg(a, b, c, d, x[i + 1] ?? 0, 5, -165796510);
		d = gg(d, a, b, c, x[i + 6] ?? 0, 9, -1069501632);
		c = gg(c, d, a, b, x[i + 11] ?? 0, 14, 643717713);
		b = gg(b, c, d, a, x[i + 0] ?? 0, 20, -373897302);
		a = gg(a, b, c, d, x[i + 5] ?? 0, 5, -701558691);
		d = gg(d, a, b, c, x[i + 10] ?? 0, 9, 38016083);
		c = gg(c, d, a, b, x[i + 15] ?? 0, 14, -660478335);
		b = gg(b, c, d, a, x[i + 4] ?? 0, 20, -405537848);
		a = gg(a, b, c, d, x[i + 9] ?? 0, 5, 568446438);
		d = gg(d, a, b, c, x[i + 14] ?? 0, 9, -1019803690);
		c = gg(c, d, a, b, x[i + 3] ?? 0, 14, -187363961);
		b = gg(b, c, d, a, x[i + 8] ?? 0, 20, 1163531501);
		a = gg(a, b, c, d, x[i + 13] ?? 0, 5, -1444681467);
		d = gg(d, a, b, c, x[i + 2] ?? 0, 9, -51403784);
		c = gg(c, d, a, b, x[i + 7] ?? 0, 14, 1735328473);
		b = gg(b, c, d, a, x[i + 12] ?? 0, 20, -1926607734);

		a = hh(a, b, c, d, x[i + 5] ?? 0, 4, -378558);
		d = hh(d, a, b, c, x[i + 8] ?? 0, 11, -2022574463);
		c = hh(c, d, a, b, x[i + 11] ?? 0, 16, 1839030562);
		b = hh(b, c, d, a, x[i + 14] ?? 0, 23, -35309556);
		a = hh(a, b, c, d, x[i + 1] ?? 0, 4, -1530992060);
		d = hh(d, a, b, c, x[i + 4] ?? 0, 11, 1272893353);
		c = hh(c, d, a, b, x[i + 7] ?? 0, 16, -155497632);
		b = hh(b, c, d, a, x[i + 10] ?? 0, 23, -1094730640);
		a = hh(a, b, c, d, x[i + 13] ?? 0, 4, 681279174);
		d = hh(d, a, b, c, x[i + 0] ?? 0, 11, -358537222);
		c = hh(c, d, a, b, x[i + 3] ?? 0, 16, -722521979);
		b = hh(b, c, d, a, x[i + 6] ?? 0, 23, 76029189);
		a = hh(a, b, c, d, x[i + 9] ?? 0, 4, -640364487);
		d = hh(d, a, b, c, x[i + 12] ?? 0, 11, -421815835);
		c = hh(c, d, a, b, x[i + 15] ?? 0, 16, 530742520);
		b = hh(b, c, d, a, x[i + 2] ?? 0, 23, -995338651);

		a = ii(a, b, c, d, x[i + 0] ?? 0, 6, -198630844);
		d = ii(d, a, b, c, x[i + 7] ?? 0, 10, 1126891415);
		c = ii(c, d, a, b, x[i + 14] ?? 0, 15, -1416354905);
		b = ii(b, c, d, a, x[i + 5] ?? 0, 21, -57434055);
		a = ii(a, b, c, d, x[i + 12] ?? 0, 6, 1700485571);
		d = ii(d, a, b, c, x[i + 3] ?? 0, 10, -1894986606);
		c = ii(c, d, a, b, x[i + 10] ?? 0, 15, -1051523);
		b = ii(b, c, d, a, x[i + 1] ?? 0, 21, -2054922799);
		a = ii(a, b, c, d, x[i + 8] ?? 0, 6, 1873313359);
		d = ii(d, a, b, c, x[i + 15] ?? 0, 10, -30611744);
		c = ii(c, d, a, b, x[i + 6] ?? 0, 15, -1560198380);
		b = ii(b, c, d, a, x[i + 13] ?? 0, 21, 1309151649);
		a = ii(a, b, c, d, x[i + 4] ?? 0, 6, -145523070);
		d = ii(d, a, b, c, x[i + 11] ?? 0, 10, -1120210379);
		c = ii(c, d, a, b, x[i + 2] ?? 0, 15, 718787259);
		b = ii(b, c, d, a, x[i + 9] ?? 0, 21, -343485551);

		a = (a + olda) | 0;
		b = (b + oldb) | 0;
		c = (c + oldc) | 0;
		d = (d + oldd) | 0;
	}

	return [a, b, c, d];
};

const add32 = (a: number, b: number): number => (a + b) | 0;

const rotl = (n: number, s: number): number => (n << s) | (n >>> (32 - s));

const cmn = (q: number, a: number, b: number, x: number, s: number, t: number): number => {
	return add32(rotl(add32(add32(a, q), add32(x, t)), s), b);
};

const ff = (a: number, b: number, c: number, d: number, x: number, s: number, t: number): number =>
	cmn((b & c) | (~b & d), a, b, x, s, t);

const gg = (a: number, b: number, c: number, d: number, x: number, s: number, t: number): number =>
	cmn((b & d) | (c & ~d), a, b, x, s, t);

const hh = (a: number, b: number, c: number, d: number, x: number, s: number, t: number): number =>
	cmn(b ^ c ^ d, a, b, x, s, t);

const ii = (a: number, b: number, c: number, d: number, x: number, s: number, t: number): number =>
	cmn(c ^ (b | ~d), a, b, x, s, t);
