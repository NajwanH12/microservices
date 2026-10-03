const test = require('node:test');
const assert = require('node:assert/strict');
const productModel = require('./models/productModel');

test('product CRUD and image validation', async (t) => {
    const products = new Map();
    let nextId = 1;
    productModel.getAllProducts = async () => Array.from(products.values());
    productModel.getProductById = async (id) => products.get(Number(id));
    productModel.createProduct = async (product) => {
        const id = nextId++;
        products.set(id, { id, ...product });
        return id;
    };
    productModel.updateProduct = async (id, product) => {
        id = Number(id);
        if (!products.has(id)) return undefined;
        products.set(id, { id, ...product });
        return products.get(id);
    };
    productModel.deleteProduct = async (id) => products.delete(Number(id));

    const app = require('./app');
    const server = app.listen(0);
    await new Promise((resolve) => server.once('listening', resolve));
    t.after(() => new Promise((resolve, reject) => server.close((error) => error ? reject(error) : resolve())));

    const baseUrl = `http://127.0.0.1:${server.address().port}/products`;
    const image = Buffer.from('small image').toString('base64');
    const payload = { name: 'Produk test', description: 'uji', price: 1000, stock: 2, image };
    const jsonRequest = (url, method, body) => fetch(url, {
        method,
        headers: { 'content-type': 'application/json' },
        body: JSON.stringify(body),
    });

    await t.test('POST creates product and GET returns image', async () => {
        const createdResponse = await jsonRequest(baseUrl, 'POST', payload);
        assert.equal(createdResponse.status, 201);
        const created = await createdResponse.json();
        assert.equal(created.data.image, image);

        const readResponse = await fetch(`${baseUrl}/${created.data.id}`);
        assert.equal(readResponse.status, 200);
        assert.equal((await readResponse.json()).data.image, image);
    });

    await t.test('PUT updates image and DELETE removes product', async () => {
        const updatedImage = Buffer.from('updated image').toString('base64');
        const response = await jsonRequest(`${baseUrl}/1`, 'PUT', { ...payload, image: updatedImage });
        assert.equal(response.status, 200);
        assert.equal((await response.json()).data.image, updatedImage);

        const deleted = await fetch(`${baseUrl}/1`, { method: 'DELETE' });
        assert.equal(deleted.status, 200);
        assert.equal((await fetch(`${baseUrl}/1`)).status, 404);
    });

    await t.test('POST and PUT reject a missing or empty image with the required message', async () => {
        for (const [url, method] of [[baseUrl, 'POST'], [`${baseUrl}/99`, 'PUT']]) {
            for (const imageValue of [undefined, '']) {
                const body = { ...payload };
                if (imageValue !== undefined) body.image = imageValue;
                else delete body.image;
                const response = await jsonRequest(url, method, body);
                assert.equal(response.status, 400);
                assert.equal((await response.json()).message, 'Field image wajib diisi');
            }
        }
    });

    await t.test('POST and PUT reject invalid Base64', async () => {
        for (const [url, method] of [[baseUrl, 'POST'], [`${baseUrl}/99`, 'PUT']]) {
            const response = await jsonRequest(url, method, { ...payload, image: 'aGVsbG8' });
            assert.equal(response.status, 400);
            assert.equal((await response.json()).message, 'Field image harus berupa Base64 yang valid');
        }
    });

    await t.test('POST and PUT reject decoded images over 2 MiB', async () => {
        const exactlyTwoMiB = Buffer.alloc(2 * 1024 * 1024).toString('base64');
        const accepted = await jsonRequest(baseUrl, 'POST', { ...payload, image: exactlyTwoMiB });
        assert.equal(accepted.status, 201);

        const tooLarge = Buffer.alloc(2 * 1024 * 1024 + 1).toString('base64');
        for (const [url, method] of [[baseUrl, 'POST'], [`${baseUrl}/99`, 'PUT']]) {
            const response = await jsonRequest(url, method, { ...payload, image: tooLarge });
            assert.equal(response.status, 400);
            assert.equal((await response.json()).message, 'Ukuran image maksimal 2 MB');
        }

        const beyondJsonLimit = Buffer.alloc(2.5 * 1024 * 1024).toString('base64');
        const oversizedRequest = await jsonRequest(baseUrl, 'POST', { ...payload, image: beyondJsonLimit });
        assert.equal(oversizedRequest.status, 400);
        assert.equal((await oversizedRequest.json()).message, 'Ukuran image maksimal 2 MB');
    });
});
