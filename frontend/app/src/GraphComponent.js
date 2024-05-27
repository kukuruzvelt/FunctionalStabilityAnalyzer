import React, { useState, useCallback } from 'react';
import ReactFlow, { addEdge, MiniMap, Controls, Background, ReactFlowProvider, useNodesState, useEdgesState, useReactFlow } from 'react-flow-renderer';
import axios from 'axios';
import { Button, TextField, Table, TableBody, TableCell, TableContainer, TableHead, TableRow, Paper, Dialog, DialogTitle, DialogContent, DialogActions } from '@mui/material';

let nodeId = 4; // начальный id для новых узлов

const GraphComponent = () => {
    const [nodes, setNodes, onNodesChange] = useNodesState([
        { id: '1', data: { label: '1' }, position: { x: 250, y: 5 } },
        { id: '2', data: { label: '2' }, position: { x: 100, y: 100 } },
        { id: '3', data: { label: '3' }, position: { x: 400, y: 100 } }
    ]);
    const [edges, setEdges, onEdgesChange] = useEdgesState([
        { id: 'e1-2', source: '1', target: '2', label: '0.5' },
        { id: 'e1-3', source: '1', target: '3', label: '0.7' }
    ]);

    const { project } = useReactFlow();
    const [tableData, setTableData] = useState([]);
    const [targetProbability, setTargetProbability] = useState(0.5);
    const [edgeDialogOpen, setEdgeDialogOpen] = useState(false);
    const [newEdge, setNewEdge] = useState({ id: '', source: '', target: '', value: 0.5 });

    const [edgeNodes, setEdgeNodes] = useState([]); // отслеживание выбранных узлов для ребра

    const handleAddNode = useCallback((event) => {
        const bounds = event.target.getBoundingClientRect();
        const position = project({ x: event.clientX - bounds.left, y: event.clientY - bounds.top });

        // Проверка, чтобы не добавлять узел при клике на существующий элемент
        const element = document.elementFromPoint(event.clientX, event.clientY);
        if (element && element.closest('.react-flow__node')) return;

        const id = `${nodeId++}`;
        setNodes((nds) => nds.concat({ id, data: { label: id }, position }));
    }, [project, setNodes]);

    const handleNodeClick = useCallback((event, node) => {
        event.stopPropagation();
        setEdgeNodes((ens) => {
            if (ens.length === 0) {
                return [node.id];
            } else if (ens.length === 1 && ens[0] !== node.id) {
                setNewEdge({ id: `e${ens[0]}-${node.id}`, source: ens[0], target: node.id, value: 0.5 });
                setEdgeDialogOpen(true);
                return [];
            }
            return ens;
        });
    }, []);

    const handleEdgeClick = useCallback((event, edge) => {
        event.stopPropagation();
        setNewEdge({ id: edge.id, source: edge.source, target: edge.target, value: parseFloat(edge.label) });
        setEdgeDialogOpen(true);
    }, []);

    const handleEdgeWeightChange = (event) => {
        setNewEdge({ ...newEdge, value: parseFloat(event.target.value) });
    };

    const handleAddEdge = () => {
        if (newEdge.value > 0 && newEdge.value <= 1) {
            setEdges((eds) => {
                const edgeIndex = eds.findIndex(e => e.id === newEdge.id);
                if (edgeIndex > -1) {
                    const updatedEdges = [...eds];
                    updatedEdges[edgeIndex].label = newEdge.value.toString();
                    return updatedEdges;
                } else {
                    return eds.concat({ id: newEdge.id, source: newEdge.source, target: newEdge.target, label: newEdge.value.toString() });
                }
            });
            setEdgeDialogOpen(false);
        } else {
            alert('Edge weight must be between 0 and 1.');
        }
    };

    const hasIsolatedNodes = (edges, nodes) => {
        const connectedNodes = new Set();
        edges.forEach(edge => {
            connectedNodes.add(edge.source);
            connectedNodes.add(edge.target);
        });
        return nodes.some(node => !connectedNodes.has(node.id));
    };

    const handleSendGraph = (endpoint) => {
        if (hasIsolatedNodes(edges, nodes)) {
            alert('There are isolated nodes in the graph. Please connect all nodes before sending.');
            return;
        }

        const graphData = {
            nodes: nodes.map(node => node.id),
            edges: edges.map(edge => ({
                source: edge.source,
                target: edge.target,
                successChance: parseFloat(edge.label)
            })),
            targetProbability: parseFloat(targetProbability)
        };

        console.log('Sending graph data to', endpoint, ':', graphData); // Вывод тела запроса в консоль

        axios.post(`https://localhost:80/api/functional_stability/${endpoint}`, graphData)
            .then(response => {
                setTableData(response.data);
            })
            .catch(error => {
                console.error('There was an error sending the graph!', error);
            });
    };

    return (
        <div style={{ height: 600 }}>
            <div style={{ height: 400 }} onClick={handleAddNode}>
                <ReactFlow
                    nodes={nodes}
                    edges={edges}
                    onNodesChange={onNodesChange}
                    onEdgesChange={onEdgesChange}
                    onNodeClick={handleNodeClick}
                    onEdgeClick={handleEdgeClick}
                    snapToGrid={true}
                    snapGrid={[15, 15]}
                    style={{ width: '100%', height: '100%' }}
                >
                    <MiniMap />
                    <Controls />
                    <Background color="#aaa" gap={16} />
                </ReactFlow>
            </div>
            <TextField
                label="Target Probability"
                type="number"
                inputProps={{ min: "0", max: "1", step: "0.001" }}
                value={targetProbability}
                onChange={(e) => setTargetProbability(parseFloat(e.target.value))}
                fullWidth
                style={{ marginTop: 20 }}
            />
            <Button variant="contained" color="primary" onClick={() => handleSendGraph('simple_search')} style={{ marginTop: 20, marginRight: 10 }}>
                Simple Search
            </Button>
            <Button variant="contained" color="secondary" onClick={() => handleSendGraph('structural_transformation')} style={{ marginTop: 20 }}>
                Structural Transformation
            </Button>
            <TableContainer component={Paper} style={{ marginTop: 20 }}>
                <Table>
                    <TableHead>
                        <TableRow>
                            <TableCell>Source</TableCell>
                            <TableCell>Target</TableCell>
                            <TableCell>Value</TableCell>
                        </TableRow>
                    </TableHead>
                    <TableBody>
                        {tableData.map((row, index) => (
                            <TableRow key={index}>
                                <TableCell>{row.source}</TableCell>
                                <TableCell>{row.target}</TableCell>
                                <TableCell>{row.value}</TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </TableContainer>

            <Dialog open={edgeDialogOpen} onClose={() => setEdgeDialogOpen(false)}>
                <DialogTitle>{newEdge.id ? 'Edit Edge' : 'Add Edge'}</DialogTitle>
                <DialogContent>
                    <TextField
                        label="Edge Weight"
                        type="number"
                        inputProps={{ min: "0", max: "1", step: "0.001" }}
                        value={newEdge.value}
                        onChange={handleEdgeWeightChange}
                        fullWidth
                    />
                </DialogContent>
                <DialogActions>
                    <Button onClick={() => setEdgeDialogOpen(false)} color="primary">
                        Cancel
                    </Button>
                    <Button onClick={handleAddEdge} color="primary">
                        {newEdge.id ? 'Save' : 'Add Edge'}
                    </Button>
                </DialogActions>
            </Dialog>
        </div>
    );
};

const GraphComponentWithProvider = () => (
    <ReactFlowProvider>
        <GraphComponent />
    </ReactFlowProvider>
);

export default GraphComponentWithProvider;
