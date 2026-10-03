import { check, sleep } from 'k6';
import http from 'k6/http';

export const options = {
    vus: 100,
    duration: '30s',

    thresholds: {
        http_req_failed: ['rate<0.05'],
        http_req_duration: ['p(95)<1000'],
    },
};

const BASE_URL = __ENV.BASE_URL || 'http://127.0.0.1:8001';
const TOKEN = __ENV.TOKEN;

export default function () {
    const payload = JSON.stringify({
        hotel_id: 1,
        room_type_id: 1,
        check_in: '2026-10-10',
        check_out: '2026-10-13',
        quantity: 1,
    });

    const response = http.post(
        `${BASE_URL}/api/v1/bookings`,
        payload,
        {
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                Authorization: `Bearer ${TOKEN}`,
                'Idempotency-Key': `load-test-${__VU}-${__ITER}-${Date.now()}`,
            },
        }
    );

    check(response, {
        'status is 201': (r) => r.status === 201,
    });

    sleep(0.1);
}