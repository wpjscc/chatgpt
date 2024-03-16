function Murmur(str, seed) {
	var key, remainder, bytes, h1, h1b, c1, c1b, c2, c2b, k1, i;

	key = new TextEncoder().encode(str);

	remainder = key.length & 3; // key.length % 4
	bytes = key.length - remainder;
	h1 = seed;
	c1 = 0xcc9e2d51;
	c2 = 0x1b873593;
	i = 0;

	while (i < bytes) {
		k1 =
		  ((key[i] & 0xff)) |
		  ((key[++i] & 0xff) << 8) |
		  ((key[++i] & 0xff) << 16) |
		  ((key[++i] & 0xff) << 24);
		++i;

		k1 = ((((k1 & 0xffff) * c1) + ((((k1 >>> 16) * c1) & 0xffff) << 16))) & 0xffffffff;
		k1 = (k1 << 15) | (k1 >>> 17);
		k1 = ((((k1 & 0xffff) * c2) + ((((k1 >>> 16) * c2) & 0xffff) << 16))) & 0xffffffff;

		h1 ^= k1;
        h1 = (h1 << 13) | (h1 >>> 19);
		h1b = ((((h1 & 0xffff) * 5) + ((((h1 >>> 16) * 5) & 0xffff) << 16))) & 0xffffffff;
		h1 = (((h1b & 0xffff) + 0x6b64) + ((((h1b >>> 16) + 0xe654) & 0xffff) << 16));
	}

	k1 = 0;

	switch (remainder) {
		case 3: k1 ^= (key[i + 2] & 0xff) << 16;
		case 2: k1 ^= (key[i + 1] & 0xff) << 8;
		case 1: k1 ^= (key[i] & 0xff);

		k1 = (((k1 & 0xffff) * c1) + ((((k1 >>> 16) * c1) & 0xffff) << 16)) & 0xffffffff;
		k1 = (k1 << 15) | (k1 >>> 17);
		k1 = (((k1 & 0xffff) * c2) + ((((k1 >>> 16) * c2) & 0xffff) << 16)) & 0xffffffff;
		h1 ^= k1;
	}

	h1 ^= key.length;

	h1 ^= h1 >>> 16;
	h1 = (((h1 & 0xffff) * 0x85ebca6b) + ((((h1 >>> 16) * 0x85ebca6b) & 0xffff) << 16)) & 0xffffffff;
	h1 ^= h1 >>> 13;
	h1 = ((((h1 & 0xffff) * 0xc2b2ae35) + ((((h1 >>> 16) * 0xc2b2ae35) & 0xffff) << 16))) & 0xffffffff;
	h1 ^= h1 >>> 16;

	return h1 >>> 0;
}

Mermaid = mermaid
const htmlEntities = (str) =>
  String(str)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;");

const MermaidChart = (code) => {
  try {
    var needsUniqueId = "render" + Murmur(code, 42).toString() + parseInt(Math.random() * 1000000);
    Mermaid.mermaidAPI.render(needsUniqueId, code, (sc) => {
      code = sc;
    });
    return `<div class="mermaid">${code}</div>`;
  } catch (err) {
    return `<pre>${htmlEntities(err.name)}: ${htmlEntities(err.message)}</pre>`;
  }
};

const MermaidPlugIn = (md, opts) => {
  Object.assign(MermaidPlugIn.default, opts);
  const { token: _token = "mermaid", ...dictionary } =
    MermaidPlugIn.default.dictionary;
  // const dictionary = swapObj(_dictionary);
  Mermaid.initialize(MermaidPlugIn.default);

  const defaultRenderer = md.renderer.rules.fence.bind(md.renderer.rules);

  function replacer(_, p1, p2, p3) {
    p1 = dictionary[p1] ?? p1;
    p2 = dictionary[p2] ?? p2;
    return p2 === "" ? `${p1}\n` : `${p1} ${p2}${p3}`;
  }

  md.renderer.rules.fence = (tokens, idx, opts, env, self) => {
    const token = tokens[idx];
    const code = token.content.trim();
    if (token.info.trim() === _token) {
      return MermaidChart(code.replace(/(.*?)[ \n](.*?)([ \n])/, replacer));
    }
    return defaultRenderer(tokens, idx, opts, env, self);
  };
};

MermaidPlugIn.default = {
  startOnLoad: false,
  securityLevel: "true",
  theme: "default",
  flowchart: {
    htmlLabels: false,
    useMaxWidth: true,
  },
  dictionary: {
    token: "mermaid",
  },
};